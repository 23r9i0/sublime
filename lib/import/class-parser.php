<?php
namespace Sublime;

/**
 *
 */

class Parser {

	public static function set( $directory ) {
		$files           = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory ) );
		$list            = array();
		/**
		 * Include Plugins by name folder, by default Exclude Plugins Folder
		 */
		$include_plugins = apply_filters( 'sublime_include_plugins', array() );
		$include_plugins = is_array( $include_plugins ) ? $include_plugins : array( $include_plugins );

		/**
		 * Exclude files to import, use file name only
		 */
		$exclude_files = apply_filters( 'sublime_exclude_files', array() );
		$exclude_files = is_array( $exclude_files ) ? $exclude_files : array( $exclude_files );

		try {
			foreach ( $files as $file ) {
				if ( 'php' !== $file->getExtension() )
					continue;

				if ( in_array( $file->getFilename(), $exclude_files ) )
					continue;

				$path = str_replace( '\\', '/', $file->getPathname() );

				if ( false !== strpos( $path, 'wp-content/plugins/sublime/missing' ) ) {
					$list[] = $path;
				} elseif ( false !== strpos( $path, 'wp-content/plugins' ) ) {
					foreach ( $include_plugins as $include_plugin ) {
						if ( false !== strpos( $path, $include_plugin ) ) {
							$list[] = $path;
						}
					}
				} else {
					$list[] = $path;
				}
			}

		} catch ( \UnexceptedValueException $e ) {
			return new \WP_Error(
				'unexcepted_value_exception',
				sprintf( 'Directory [%s] contained a directory we can not recurse into', $directory )
			);
		}

		return $list;
	}

	public static function get( $files, $root ) {
		$output = array();

		foreach ( $files as $filename ) {
			$file = new \WP_Parser\File_Reflector( $filename );
			$path = str_replace( '\\', '/', substr( $filename, strlen( $root ) ) );
			$path = ltrim( $path, '/' );

			$file->setFilename( $path );
			$file->process();

			// TODO proper exporter
			$out = array(
				'file' => \WP_Parser\export_docblock( $file ),
				'path' => $file->getFilename(),
				'root' => $root,
			);

			if ( ! empty( $file->uses ) ) {
				$out['uses'] = \WP_Parser\export_uses( $file->uses );
			}

			foreach ( $file->getIncludes() as $include ) {
				$out['includes'][] = array(
					'name' => $include->getName(),
					'line' => $include->getLineNumber(),
					'type' => $include->getType(),
				);
			}

			foreach ( $file->getConstants() as $constant ) {
				$out['constants'][] = array(
					'name'  => $constant->getShortName(),
					'line'  => $constant->getLineNumber(),
					'value' => $constant->getValue(),
					'doc'   => \WP_Parser\export_docblock( $constant ),
				);
			}

			if ( ! empty( $file->uses['hooks'] ) ) {
				$out['hooks'] = \WP_Parser\export_hooks( $file->uses['hooks'] );
			}

			foreach ( $file->getFunctions() as $function ) {
				$func = array(
					'name'      => $function->getShortName(),
					'line'      => $function->getLineNumber(),
					'end_line'  => $function->getNode()->getAttribute( 'endLine' ),
					'arguments' => \WP_Parser\export_arguments( $function->getArguments() ),
					'doc'       => \WP_Parser\export_docblock( $function ),
					'hooks'     => array(),
				);

				if ( ! empty( $function->uses ) ) {
					$func['uses'] = \WP_Parser\export_uses( $function->uses );

					if ( ! empty( $function->uses['hooks'] ) ) {
						$func['hooks'] = \WP_Parser\export_hooks( $function->uses['hooks'] );
					}
				}

				$out['functions'][] = $func;
			}

			foreach ( $file->getClasses() as $class ) {
				$class_data = array(
					'name'       => $class->getShortName(),
					'line'       => $class->getLineNumber(),
					'end_line'   => $class->getNode()->getAttribute( 'endLine' ),
					'final'      => $class->isFinal(),
					'abstract'   => $class->isAbstract(),
					'extends'    => $class->getParentClass(),
					'implements' => $class->getInterfaces(),
					'properties' => \WP_Parser\export_properties( $class->getProperties() ),
					'methods'    => \WP_Parser\export_methods( $class->getMethods() ),
					'doc'        => \WP_Parser\export_docblock( $class ),
				);

				$out['classes'][] = $class_data;
			}

			$output[] = $out;
		}

		return $output;
	}
}
