<?php
/**
 * Minimal XLSX reader (Office Open XML).
 * Uses ZipArchive when available, otherwise pure-PHP ZIP (ATEP_Zip).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATEP_XLSX {

	/**
	 * Parse an .xlsx file into sheets.
	 *
	 * @param string $path Absolute path.
	 * @return array|WP_Error List of sheets: name, rows (array of string cells).
	 */
	public static function parse( $path ) {
		$zip = self::open_zip( $path );
		if ( is_wp_error( $zip ) ) {
			return $zip;
		}

		$shared = self::shared_strings( $zip );
		$sheets = self::workbook_sheets( $zip );

		if ( empty( $sheets ) ) {
			self::close_zip( $zip );
			return new WP_Error( 'atep_no_sheets', __( 'В книге нет листов.', 'at-excel-price' ) );
		}

		$result = array();

		foreach ( $sheets as $sheet ) {
			$xml = self::zip_get( $zip, $sheet['path'] );
			if ( false === $xml || '' === $xml ) {
				continue;
			}
			$rows = self::sheet_rows( $xml, $shared );
			$result[] = array(
				'name' => $sheet['name'],
				'rows' => $rows,
			);
		}

		self::close_zip( $zip );

		if ( empty( $result ) ) {
			return new WP_Error( 'atep_empty', __( 'Не удалось прочитать листы файла.', 'at-excel-price' ) );
		}

		return $result;
	}

	/**
	 * @param string $path Path.
	 * @return ZipArchive|ATEP_Zip|WP_Error
	 */
	private static function open_zip( $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'atep_unreadable', __( 'Файл недоступен для чтения.', 'at-excel-price' ) );
		}

		if ( class_exists( 'ZipArchive' ) && ! defined( 'ATEP_FORCE_PURE_ZIP' ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $path ) ) {
				return $zip;
			}
		}

		if ( ! function_exists( 'gzinflate' ) ) {
			return new WP_Error(
				'atep_no_zlib',
				__( 'Нужно расширение zlib (функция gzinflate) или ZipArchive для чтения .xlsx.', 'at-excel-price' )
			);
		}

		return ATEP_Zip::open( $path );
	}

	/**
	 * @param ZipArchive|ATEP_Zip $zip Zip handle.
	 * @param string              $name Entry name.
	 * @return string|false
	 */
	private static function zip_get( $zip, $name ) {
		if ( $zip instanceof ZipArchive ) {
			$data = $zip->getFromName( $name );
			return false === $data ? false : $data;
		}
		if ( $zip instanceof ATEP_Zip ) {
			return $zip->get_from_name( $name );
		}
		return false;
	}

	/**
	 * @param ZipArchive|ATEP_Zip $zip Zip handle.
	 */
	private static function close_zip( $zip ) {
		if ( $zip instanceof ZipArchive ) {
			$zip->close();
		}
	}

	/**
	 * @param ZipArchive|ATEP_Zip $zip Zip.
	 * @return string[]
	 */
	private static function shared_strings( $zip ) {
		$xml = self::zip_get( $zip, 'xl/sharedStrings.xml' );
		if ( false === $xml || '' === $xml ) {
			return array();
		}

		$doc = self::load_xml( $xml );
		if ( ! $doc ) {
			return array();
		}

		$strings = array();
		$nodes   = $doc->getElementsByTagName( 'si' );

		foreach ( $nodes as $si ) {
			$text = '';
			$ts   = $si->getElementsByTagName( 't' );
			foreach ( $ts as $t ) {
				$text .= $t->textContent;
			}
			$strings[] = $text;
		}

		return $strings;
	}

	/**
	 * @param ZipArchive|ATEP_Zip $zip Zip.
	 * @return array<int, array{name:string,path:string}>
	 */
	private static function workbook_sheets( $zip ) {
		$workbook = self::zip_get( $zip, 'xl/workbook.xml' );
		$rels     = self::zip_get( $zip, 'xl/_rels/workbook.xml.rels' );

		if ( false === $workbook || false === $rels ) {
			return array();
		}

		$rel_map = array();
		$rel_doc = self::load_xml( $rels );
		if ( $rel_doc ) {
			foreach ( $rel_doc->getElementsByTagName( 'Relationship' ) as $rel ) {
				$id     = $rel->getAttribute( 'Id' );
				$target = $rel->getAttribute( 'Target' );
				$target = ltrim( str_replace( '\\', '/', $target ), '/' );
				if ( 0 === strpos( $target, 'xl/' ) ) {
					$path = $target;
				} else {
					$path = 'xl/' . $target;
				}
				$rel_map[ $id ] = $path;
			}
		}

		$doc = self::load_xml( $workbook );
		if ( ! $doc ) {
			return array();
		}

		$sheets = array();
		foreach ( $doc->getElementsByTagName( 'sheet' ) as $sheet ) {
			$name = $sheet->getAttribute( 'name' );
			$rid  = $sheet->getAttributeNS( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id' );
			if ( '' === $rid ) {
				$rid = $sheet->getAttribute( 'r:id' );
			}
			if ( ! isset( $rel_map[ $rid ] ) ) {
				continue;
			}
			$sheets[] = array(
				'name' => $name ? $name : __( 'Лист', 'at-excel-price' ),
				'path' => $rel_map[ $rid ],
			);
		}

		return $sheets;
	}

	/**
	 * @param string   $xml     Sheet XML.
	 * @param string[] $shared  Shared strings.
	 * @return array<int, array<int, string>>
	 */
	private static function sheet_rows( $xml, $shared ) {
		$doc = self::load_xml( $xml );
		if ( ! $doc ) {
			return array();
		}

		$rows = array();

		foreach ( $doc->getElementsByTagName( 'row' ) as $row ) {
			$cells    = array();
			$max_col  = 0;
			$next_col = 0;

			foreach ( $row->getElementsByTagName( 'c' ) as $cell ) {
				// Some writers omit r="A1"; fall back to sequential columns.
				$ref = $cell->getAttribute( 'r' );
				$col = ( '' !== $ref ) ? self::column_index( $ref ) : -1;
				if ( $col < 0 ) {
					$col = $next_col;
				}
				$next_col = $col + 1;

				$cells[ $col ] = self::cell_value( $cell, $shared );
				if ( $col > $max_col ) {
					$max_col = $col;
				}
			}

			if ( empty( $cells ) ) {
				continue;
			}

			$line = array();
			for ( $i = 0; $i <= $max_col; $i++ ) {
				$line[] = isset( $cells[ $i ] ) ? $cells[ $i ] : '';
			}

			if ( self::row_is_empty( $line ) ) {
				continue;
			}

			$rows[] = $line;
		}

		return self::normalize_width( $rows );
	}

	/**
	 * @param DOMElement $cell   Cell.
	 * @param string[]   $shared Shared strings.
	 * @return string
	 */
	private static function cell_value( $cell, $shared ) {
		$type = $cell->getAttribute( 't' );

		if ( 'inlineStr' === $type ) {
			$text = '';
			foreach ( $cell->getElementsByTagName( 't' ) as $t ) {
				$text .= $t->textContent;
			}
			return $text;
		}

		if ( 's' === $type ) {
			$v = self::first_child_text( $cell, 'v' );
			$i = (int) $v;
			return isset( $shared[ $i ] ) ? $shared[ $i ] : '';
		}

		if ( 'b' === $type ) {
			$v = self::first_child_text( $cell, 'v' );
			return '1' === $v ? 'TRUE' : 'FALSE';
		}

		if ( 'str' === $type ) {
			return self::first_child_text( $cell, 'v' );
		}

		return self::first_child_text( $cell, 'v' );
	}

	/**
	 * @param DOMElement $cell Cell.
	 * @param string     $tag  Tag.
	 * @return string
	 */
	private static function first_child_text( $cell, $tag ) {
		foreach ( $cell->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->localName === $tag ) {
				return $child->textContent;
			}
		}
		$list = $cell->getElementsByTagName( $tag );
		if ( $list->length > 0 ) {
			return $list->item( 0 )->textContent;
		}
		return '';
	}

	/**
	 * @param string $ref Cell ref like B12.
	 * @return int Zero-based column or -1.
	 */
	private static function column_index( $ref ) {
		if ( ! preg_match( '/^([A-Z]+)/i', $ref, $m ) ) {
			return -1;
		}
		$letters = strtoupper( $m[1] );
		$index   = 0;
		$len     = strlen( $letters );
		for ( $i = 0; $i < $len; $i++ ) {
			$index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return $index - 1;
	}

	/**
	 * @param string[] $row Row.
	 * @return bool
	 */
	private static function row_is_empty( $row ) {
		foreach ( $row as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<int, array<int, string>> $rows Rows.
	 * @return array<int, array<int, string>>
	 */
	private static function normalize_width( $rows ) {
		$width = 0;
		foreach ( $rows as $row ) {
			$width = max( $width, count( $row ) );
		}
		if ( $width < 1 ) {
			return array();
		}
		foreach ( $rows as $i => $row ) {
			while ( count( $row ) < $width ) {
				$row[] = '';
			}
			$rows[ $i ] = $row;
		}
		return $rows;
	}

	/**
	 * @param string $xml XML.
	 * @return DOMDocument|null
	 */
	private static function load_xml( $xml ) {
		if ( '' === $xml ) {
			return null;
		}
		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$ok   = $doc->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $ok ? $doc : null;
	}
}
