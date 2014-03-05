<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Structurebrowser component controller.
 *
 * @category   OntoWiki
 * @package    Extensions_Structurebrowser
 * @author     Natanael Arndt <arndt@informatik.uni-leipzig.de>
 * @copyright  Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class StructurebrowserController extends OntoWiki_Controller_Component
{
    public function init ()
    {
        parent::init();
        define('VOIDX_NS', 'http://rdfs.org/ns/void-ext#');
        define('VOID_NS', 'http://rdfs.org/ns/void#');
        define('VOIDX_hasStructure', VOIDX_NS . 'hasStructure');
        define('VOIDX_hasPredicate', VOIDX_NS . 'hasPredicate');
        define('VOIDX_hasRange', VOIDX_NS . 'hasRange');
        define('VOIDX_domain', VOIDX_NS . 'domain');
        define('VOIDX_predicate', VOIDX_NS . 'predicate');
        define('VOIDX_range', VOIDX_NS . 'range');
        define('RDFS_Literal', EF_RDFS_NS . 'Literal');
        define('VOID_Dataset', VOID_NS . 'Dataset');
        define('VOID_inDataset', VOID_NS . 'inDataset');
    }

    public function browseAction()
    {
        $resourceUri = $this->_request->resourceUri;
        $nodeUri = $this->_request->nodeUri;

        // tells the OntoWiki to not apply the template to this action
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        if ($resourceUri == null) {
            $return = null;
        } else {
            if ($nodeUri == null) {
                $query = 'prefix voidx: <' . VOIDX_NS . '>' . PHP_EOL;
                $query.= 'select distinct ?domain ?predicate ?range {' . PHP_EOL;
                $query.= '  <' . $resourceUri . '> voidx:hasStructure ?structure .' . PHP_EOL;
                $query.= '  ?structure voidx:domain ?domain ;' . PHP_EOL;
                $query.= '      voidx:hasPredicate ?predicateStructure .' . PHP_EOL;
                $query.= '  ?predicateStructure voidx:predicate ?predicate ;' . PHP_EOL;
                $query.= '      voidx:range ?range . ' . PHP_EOL;
                $query.= '  filter((?predicate != <' . VOID_inDataset . '>) && (?range != <' . RDFS_Literal . '>))' . PHP_EOL;
                $query.= '} ' . PHP_EOL;
            } else {
                // TODO see if wee need this
            }

            $owApp = OntoWiki::getInstance();

            $logger = $owApp->logger;
            $model = $owApp->selectedModel;

            $result = $model->sparqlQuery($query);

            $return = array();
            foreach ($result as $row) {
                $domain = $row['domain'];
                $predicate = $row['predicate'];
                $range = $row['range'];
                if (!isset($return[$domain])) {
                    $return[$domain] = array();
                }
                if (!isset($return[$domain][$predicate])) {
                    $return[$domain][$predicate] = array();
                }
                $return[$domain][$predicate][] = $range;
            }
        }

        $this->_response->setBody(json_encode($return));
    }
}
