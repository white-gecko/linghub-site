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
        $modelUri = $model->getModelIri();

        $r = new Erfurt_Rdf_Resource($datasetUri);

        // Try to instanciate the requested wrapper
        $wrapperName = 'Linkeddata';
        $wrapper = Erfurt_Wrapper_Registry::getInstance()->getWrapperInstance($wrapperName);

        $wrapperResult = null;
        $wrapperResult = $wrapper->run($r, $modelUri, true);

        $newStatements = null;
        if ($wrapperResult === false) {
            // IMPORT_WRAPPER_NOT_AVAILABLE;
            $logger->err('LinkeddataHelper: Wrapper result is false on fetching: "' . $datasetUri . '"');
        } else if (is_array($wrapperResult)) {
            $newStatements = $wrapperResult['add'];
            // TODO make sure to only import the specified resource
            $newModel = new Erfurt_Rdf_MemoryModel($newStatements);
            $newStatements = array();
            $newStatements[$datasetUri] = $newModel->getPO($datasetUri);
        } else {
            // IMPORT_WRAPPER_ERR;
            $logger->err('LinkeddataHelper: Import error on fetching: "' . $datasetUri . '"');
        }

        $this->_response->setBody(json_encode($newStatements));

    }

}
