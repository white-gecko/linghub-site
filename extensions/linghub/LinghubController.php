<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Linghub component controller.
 *
 * @category   OntoWiki
 * @package    Extensions_Linghub
 * @author     Henri Knochenhauer <hknochi@gmail.com>
 * @author     Natanael Arndt <arndt@informatik.uni-leipzig.de>
 * @copyright  Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class LinghubController extends OntoWiki_Controller_Component
{
    public function init ()
    {
        parent::init();
        define('LH_DCTERMS_NS', 'http://purl.org/dc/terms/');
        define('LH_FOAF_NS',    'http://xmlns.com/foaf/0.1/');
        define('LH_VOID_NS',    'http://rdfs.org/ns/void#');
        define('LH_LING_NS',    'http://lgd.aksw.org/LingHub/vocab/');
        define('LH_PROV_NS',    'http://www.w3.org/ns/prov#');
    }
    public function adddatasetAction()
    {
        // tells the OntoWiki to not apply the template to this action
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        $datasetUri = $this->_request->datasetUri;

        $owApp = OntoWiki::getInstance();

        $logger = $owApp->logger;
        $model = $owApp->selectedModel;

        if (!($model instanceof Erfurt_Rdf_Model)) {
            $result = array(
                'type' => 'error',
                'message' => 'The model is not available because of a session timeout. Please reload this page.'
            );
            $this->_response->setBody(json_encode($result));
            return;
        }

        $modelUri = $model->getModelIri();

        $r = new Erfurt_Rdf_Resource($datasetUri);

        // Try to instanciate the requested wrapper
        $wrapperName = 'Linkeddata';
        $wrapper = Erfurt_Wrapper_Registry::getInstance()->getWrapperInstance($wrapperName);

        $wrapperResult = null;
        $wrapperResult = $wrapper->run($r, $modelUri, true);

        $result = null;

        if ($wrapperResult === false) {
            $logger->err('LinkeddataHelper: Wrapper result is false on fetching: "' . $datasetUri . '"');
            $result = array(
                'type' => 'error',
                'message' => 'No Linked Data was found for the given resource ("' . $datasetUri . '").'
            );

            // TODO if no Linked Data was found try some other methods to get a description
            // Possible other methods: add "/sparql" and send a query
        } else if (is_array($wrapperResult)) {
            $newStatements = $wrapperResult['add'];
            // TODO make sure to only import the specified resource
            $newModel = new Erfurt_Rdf_MemoryModel($newStatements);
            $newStatements = array();
            $newStatements[$datasetUri] = $newModel->getPO($datasetUri);

            $newStatements = $this->_filterByApplicationProfile($newStatements);

            $newStatements[$datasetUri][EF_RDF_TYPE][] = array(
                'type' => 'uri', 'value' => LH_VOID_NS . 'Dataset'
            );
            // Add the found statements to our model
            $model->addMultipleStatements($newStatements);

            $titles = $this->_getAllTitles($newStatements, $model);

            $result = array(
                'type' => 'data',
                'content' => $newStatements,
                'titles' => $titles
            );
        } else {
            // IMPORT_WRAPPER_ERR;
            $logger->err('LinkeddataHelper: Import error on fetching: "' . $datasetUri . '"');
            $result = array(
                'type' => 'error',
                'message' => 'There was an import error on fetching the given resource ("' . $datasetUri . '").'
            );
        }

        $this->_response->setBody(json_encode($result));
    }

    private function _getAllTitles ($newStatements, $model)
    {
        $titleHelper = new OntoWiki_Model_TitleHelper($model);
        foreach ($newStatements as $resourceUri => $resourceDescription) {
            $titleHelper->addResource($resourceUri);
            foreach ($resourceDescription as $predicateUri => $properties) {
                $titleHelper->addResource($predicateUri);
            }
        }

        $resources = $titleHelper->getResources();

        $titles = array();
        foreach ($resources as $resourceUri) {
            $titles[$resourceUri] = $titleHelper->getTitle($resourceUri);
        }

        return $titles;
    }

    private function _trySparqlEndpoint ($uri)
    {
        if (strstr($uri, -1) == '/') {
            $endpointUri = $uri . 'sparql'
        } else {
            $endpointUri = $uri . '/sparql'
        }


    }

    private function _filterByApplicationProfile ($statements, $type = null)
    {
        $applicationProfile = array(
            EF_RDF_TYPE,
            LH_DCTERMS_NS . 'title',
            LH_DCTERMS_NS . 'description',
            LH_DCTERMS_NS . 'license',
            LH_DCTERMS_NS . 'created',
            LH_DCTERMS_NS . 'issued',
            LH_DCTERMS_NS . 'modified',
            LH_DCTERMS_NS . 'subject',
            LH_DCTERMS_NS . 'creator',
            LH_DCTERMS_NS . 'publisher',
            LH_DCTERMS_NS . 'contributor',
            LH_DCTERMS_NS . 'relation',
            LH_FOAF_NS . 'homepage',
            LH_FOAF_NS . 'page',
            LH_VOID_NS . 'feature',
            LH_VOID_NS . 'triples',
            LH_VOID_NS . 'rootResource',
            LH_VOID_NS . 'exampleResource',
            LH_VOID_NS . 'uriSpace',
            LH_VOID_NS . 'sparqlEndpoint',
            LH_VOID_NS . 'dataDump',
            LH_LING_NS . 'language',
            LH_LING_NS . 'version',
            LH_LING_NS . 'latestVersion',
            LH_LING_NS . 'links',
            LH_LING_NS . 'subDataset',
            LH_LING_NS . 'superDataset',
            LH_PROV_NS . 'wasGeneratedBy',
            LH_PROV_NS . 'wasDerivedFrom',
        );

        $filteredStatements = array();
        foreach ($statements as $resourceUri => $description) {
            $filteredStatements[$resourceUri] = array();
            foreach ($description as $predicateUri => $properties) {
                if (in_array($predicateUri, $applicationProfile)) {
                    $filteredStatements[$resourceUri][$predicateUri] = $properties;
                }
            }
        }

        return $filteredStatements;
    }
}
