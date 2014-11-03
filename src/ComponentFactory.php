<?php
/**
 * File containing the ComponentFactory class
 *
 * This file is part of the MediaWiki skin Chameleon.
 *
 * @copyright 2013 - 2014, Stephan Gambke
 * @license   GNU General Public License, version 3 (or any later version)
 *
 * The Chameleon skin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * The Chameleon skin is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup Skins
 */

namespace Skins\Chameleon;

use DOMDocument;
use RuntimeException;
use Skins\Chameleon\Components\Component;
use Skins\Chameleon\Components\Container;

/**
 * Class ComponentFactory
 *
 * @author Stephan Gambke
 * @since 1.0
 * @ingroup Skins
 */
class ComponentFactory {

	// the root component of the page; should be of type Container
	private $mRootComponent = null;

	private $layoutFile;
	private $skinTemplate;

	/**
	 * @param string $layoutFileName
	 */
	function __construct( $layoutFileName ) {
		$this->setLayoutFile( $layoutFileName );
	}

	/**
	 * @return Container
	 * @throws \MWException
	 */
	public function getRootComponent() {

		if ( $this->mRootComponent === null ) {

			$doc = new DOMDocument();

			$doc->load( $this->getLayoutFile() );

			$doc->normalizeDocument();

			$roots = $doc->getElementsByTagName( 'structure' );

			if ( $roots->length > 0 ) {

				$this->mRootComponent = $this->getComponent( $roots->item( 0 ) );

			} else {
				// TODO: catch other errors, e.g. malformed XML
				throw new \MWException( sprintf( '%s: XML description is missing an element: structure.', $this->getLayoutFile() ) );
			}
		}

		return $this->mRootComponent;

	}

	/**
	 * @param \DOMElement $description
	 * @param int         $indent
	 * @param string      $htmlClassAttribute
	 *
	 * @throws \MWException
	 * @return \Skins\Chameleon\Components\Container
	 */
	public function getComponent( \DOMElement $description, $indent = 0, $htmlClassAttribute = '' ) {

		$className = $this->getComponentClassName( $description );
		$component = new $className( $this->getSkinTemplate(), $description, $indent, $htmlClassAttribute );

		$children = $description->childNodes;

		foreach ( $children as $child ) {
			if ( is_a( $child, 'DOMElement' ) && strtolower( $child->nodeName ) === 'modification' ) {
				$component = $this->getModifiedComponent( $child, $component );
			}
		}

		return $component;
	}

	/**
	 * @param \DOMElement $description
	 * @param Component   $component
	 *
	 * @return mixed
	 * @throws \MWException
	 */
	protected function getModifiedComponent( \DOMElement $description, Component $component ) {

		if ( !$description->hasAttribute( 'type' ) ) {
			throw new \MWException( sprintf( '%s (line %d): Modification element missing an attribute: type.', $this->getLayoutFile(), $description->getLineNo() ) );
		}

		$className = 'Skins\\Chameleon\\Components\\Modifications\\' . $description->getAttribute( 'type' );

		if ( !class_exists( $className ) || !is_subclass_of( $className, 'Skins\\Chameleon\\Components\\Modifications\\Modification' ) ) {
			throw new \MWException( sprintf( '%s (line %d): Invalid modification type: %s.', $this->getLayoutFile(), $description->getLineNo(), $description->getAttribute( 'type' ) ) );
		}

		return new $className( $component, $description );

	}

	/**
	 * @return string
	 */
	protected function getLayoutFile() {

		return $this->layoutFile;
	}

	/**
	 * @param string $fileName
	 */
	public function setLayoutFile( $fileName ) {

		$fileName = $this->sanitizeFileName( $fileName );

		if ( !is_readable( $fileName ) ) {
			throw new RuntimeException( "Expected an accessible {$fileName} layout file" );
		}

		$this->layoutFile = $fileName;
	}

	/**
	 * @param string $fileName
	 * @return string
	 */
	public function sanitizeFileName( $fileName ) {
		return str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, $fileName );
	}

	/**
	 * @return mixed
	 */
	public function getSkinTemplate() {
		return $this->skinTemplate;
	}

	/**
	 * @param ChameleonTemplate $skinTemplate
	 */
	public function setSkinTemplate( ChameleonTemplate $skinTemplate ) {
		$this->skinTemplate = $skinTemplate;
	}

	/**
	 * @param \DOMElement $description
	 * @return string
	 * @throws \MWException
	 * @since 1.1
	 */
	protected function getComponentClassName( \DOMElement $description ) {

		$className = 'Skins\\Chameleon\\Components\\';
		$nodeName = strtolower( $description->nodeName );

		switch ( $nodeName ) {
			case 'structure':
			case 'grid':
			case 'row':
			case 'cell':
				$className .= ucfirst( $nodeName );
				break;
			case 'component':
				if ( $description->hasAttribute( 'type' ) ) {
					$className .= $description->getAttribute( 'type' );
				} else {
					$className .= 'Container';
				}
				break;
			case 'modification':
				$className .= 'Silent';
				break;
			default:
				throw new \MWException( sprintf( '%s (line %d): XML element not allowed here: %s.', $this->getLayoutFile(), $description->getLineNo(), $description->nodeName ) );
		}

		if ( ! class_exists( $className ) || !is_subclass_of( $className, 'Skins\\Chameleon\\Components\\Component' ) ) {
			throw new \MWException( sprintf( '%s (line %d): Invalid component type: %s.', $this->getLayoutFile(), $description->getLineNo(), $description->getAttribute( 'type' ) ) );
		}

		return $className;
	}


}
