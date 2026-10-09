<?php
/**
 * Read files from a ZIP archive without the ZipArchive PHP extension.
 * Supports STORE (0) and DEFLATE (8) — enough for .xlsx workbooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATEP_Zip {

	/** @var string */
	private $data = '';

	/** @var array<string, array{offset:int,comp:int,size:int,csize:int}> */
	private $entries = array();

	/**
	 * @param string $path Absolute path to ZIP.
	 * @return self|WP_Error
	 */
	public static function open( $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'atep_unreadable', __( 'Файл недоступен для чтения.', 'at-excel-price' ) );
		}

		$size = filesize( $path );
		if ( false === $size || $size < 22 ) {
			return new WP_Error( 'atep_bad_xlsx', __( 'Не удалось открыть файл Excel. Нужен формат .xlsx.', 'at-excel-price' ) );
		}

		// Guard against huge uploads exhausting memory.
		if ( $size > 32 * 1024 * 1024 ) {
			return new WP_Error( 'atep_too_large', __( 'Файл слишком большой (лимит 32 МБ).', 'at-excel-price' ) );
		}

		$data = file_get_contents( $path );
		if ( false === $data || 'PK' !== substr( $data, 0, 2 ) ) {
			return new WP_Error( 'atep_bad_xlsx', __( 'Не удалось открыть файл Excel. Нужен формат .xlsx.', 'at-excel-price' ) );
		}

		$zip = new self();
		$zip->data = $data;
		$error     = $zip->parse_central_directory();
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		return $zip;
	}

	/**
	 * @param string $name Entry path inside the archive.
	 * @return string|false
	 */
	public function get_from_name( $name ) {
		$name = str_replace( '\\', '/', $name );
		$name = ltrim( $name, '/' );

		if ( ! isset( $this->entries[ $name ] ) ) {
			// Case-insensitive / alternate separators.
			$lower = strtolower( $name );
			foreach ( $this->entries as $key => $meta ) {
				if ( strtolower( $key ) === $lower ) {
					$name = $key;
					break;
				}
			}
		}

		if ( ! isset( $this->entries[ $name ] ) ) {
			return false;
		}

		$meta   = $this->entries[ $name ];
		$offset = $meta['offset'];
		$len    = strlen( $this->data );

		if ( $offset + 30 > $len ) {
			return false;
		}

		$sig = unpack( 'V', substr( $this->data, $offset, 4 ) );
		if ( empty( $sig[1] ) || 0x04034b50 !== $sig[1] ) {
			return false;
		}

		$header = unpack(
			'vversion/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnamelen/vexlen',
			substr( $this->data, $offset + 4, 26 )
		);

		if ( ! is_array( $header ) ) {
			return false;
		}

		$data_start = $offset + 30 + $header['namelen'] + $header['exlen'];
		$csize      = (int) $header['csize'];
		$method     = (int) $header['method'];
		$size       = (int) $header['size'];

		// Prefer sizes from central directory when local header used data descriptor.
		if ( ( $header['flag'] & 0x08 ) && $meta['csize'] > 0 ) {
			$csize  = $meta['csize'];
			$size   = $meta['size'];
			$method = $meta['comp'];
		}

		if ( $data_start + $csize > $len && 0 === ( $header['flag'] & 0x08 ) ) {
			return false;
		}

		$compressed = substr( $this->data, $data_start, $csize );
		if ( false === $compressed ) {
			return false;
		}

		if ( 0 === $method ) {
			return $compressed;
		}

		if ( 8 === $method ) {
			if ( ! function_exists( 'gzinflate' ) ) {
				return false;
			}
			$out = @gzinflate( $compressed );
			if ( false === $out && function_exists( 'gzuncompress' ) ) {
				$out = @gzuncompress( $compressed );
			}
			if ( false === $out && function_exists( 'inflate_init' ) ) {
				$ctx = inflate_init( ZLIB_ENCODING_RAW );
				if ( false !== $ctx ) {
					$out = inflate_add( $ctx, $compressed, ZLIB_FINISH );
				}
			}
			return false === $out ? false : $out;
		}

		return false;
	}

	/**
	 * @return true|WP_Error
	 */
	private function parse_central_directory() {
		$len  = strlen( $this->data );
		$eocd = false;

		// End of central directory signature near the end (comment may follow).
		$scan = min( 65557, $len );
		for ( $i = $len - 22; $i >= $len - $scan && $i >= 0; $i-- ) {
			$sig = unpack( 'V', substr( $this->data, $i, 4 ) );
			if ( ! empty( $sig[1] ) && 0x06054b50 === $sig[1] ) {
				$eocd = $i;
				break;
			}
		}

		if ( false === $eocd ) {
			return new WP_Error( 'atep_bad_xlsx', __( 'Не удалось открыть файл Excel. Нужен формат .xlsx.', 'at-excel-price' ) );
		}

		$info = unpack(
			'vdisk/vstart/ventries_disk/ventries/Vsize/Voffset/vcomment',
			substr( $this->data, $eocd + 4, 18 )
		);

		if ( ! is_array( $info ) ) {
			return new WP_Error( 'atep_bad_xlsx', __( 'Не удалось открыть файл Excel. Нужен формат .xlsx.', 'at-excel-price' ) );
		}

		$pos     = (int) $info['offset'];
		$entries = (int) $info['entries'];

		for ( $n = 0; $n < $entries; $n++ ) {
			if ( $pos + 46 > $len ) {
				break;
			}
			$sig = unpack( 'V', substr( $this->data, $pos, 4 ) );
			if ( empty( $sig[1] ) || 0x02014b50 !== $sig[1] ) {
				break;
			}

			$header = unpack(
				'vmade/vversion/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnamelen/vexlen/vcomlen/vdisk/viattr/Veattr/Voffset',
				substr( $this->data, $pos + 4, 42 )
			);

			if ( ! is_array( $header ) ) {
				break;
			}

			$name = substr( $this->data, $pos + 46, $header['namelen'] );
			$name = str_replace( '\\', '/', $name );

			if ( '' !== $name && '/' !== substr( $name, -1 ) ) {
				$this->entries[ $name ] = array(
					'offset' => (int) $header['offset'],
					'comp'   => (int) $header['method'],
					'size'   => (int) $header['size'],
					'csize'  => (int) $header['csize'],
				);
			}

			$pos += 46 + $header['namelen'] + $header['exlen'] + $header['comlen'];
		}

		if ( empty( $this->entries ) ) {
			return new WP_Error( 'atep_bad_xlsx', __( 'Не удалось открыть файл Excel. Нужен формат .xlsx.', 'at-excel-price' ) );
		}

		return true;
	}
}
