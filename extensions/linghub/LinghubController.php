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
    public function adddatasetAction(){
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

            // TODO filter statements by a given application profile for void:Datasets

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

    private function _getAllTitles ($newStatements, $model) {
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
}
