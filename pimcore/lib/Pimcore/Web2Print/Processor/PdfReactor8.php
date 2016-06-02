<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Web2Print\Processor;

use Pimcore\Config;
use \Pimcore\Model\Document;
use Pimcore\Web2Print\Processor;

class PdfReactor8 extends Processor {

    protected function buildPdf(Document\PrintAbstract $document, $config) {
        $web2PrintConfig = Config::getWeb2PrintConfig();

        $params = array();
        $params['printermarks'] = $config->printermarks == "true";
        $params['screenResolutionImages'] = $config->screenResolutionImages == "true";
        $html = $document->renderDocument($params);

        $filePath = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "pdf-reactor-input-" . $document->getId() . ".html";

        file_put_contents($filePath, $html);
        $html = null;

        ini_set("default_socket_timeout", 3000);
        ini_set('max_input_time', -1);

        include_once('Pimcore/Web2Print/Processor/api/PDFreactor.class.php');


        $port = ((string) $web2PrintConfig->pdfreactorServerPort) ? (string) $web2PrintConfig->pdfreactorServerPort : "9423";

        $pdfreactor = new \PDFreactor("http://" . $web2PrintConfig->pdfreactorServer . ":" . $port . "/service/rest");

        $filePath = str_replace(PIMCORE_DOCUMENT_ROOT, "", $filePath);

        $reactorConfig = [
            "document" => (string) $web2PrintConfig->pdfreactorHostname . $filePath,
            "baseURL" => (string) $web2PrintConfig->pdfreactorHostname,
            "author" => $config->author ? $config->author : "",
            "title" => $config->title ? $config->title : "",
            "addLinks" => $config->links == "true",
            "addBookmarks" => $config->bookmarks == "true",
            "javaScriptMode" => $config->prJavaScriptMode,
            "viewerPreferences" => [$config->prViewerPreference],
            "defaultColorSpace" => $config->prColorspace,
            "encryption" => $config->prEncryption,
            "addTags" => $config->tags == "true",
            "logLevel" => $config->prLoglevel

        ];

        if(trim($web2PrintConfig->pdfreactorLicence)) {
            $reactorConfig["licenseKey"] = trim($web2PrintConfig->pdfreactorLicence);
        }

        try {
            $result = $pdfreactor->convert($reactorConfig);
            $pdf = base64_decode($result->document);
        } catch(\Exception $e) {
            \Logger::error($e);
            $document->setLastGenerateMessage($e->getMessage());

            throw new \Exception("Error during REST-Request:" . $e->getMessage());
        }

        $document->setLastGenerateMessage("");
        return $pdf;
    }

    public function getProcessingOptions() {
        include_once('Pimcore/Web2Print/Processor/api/PDFreactor.class.php');

        $options = array();

        $options[] = array("name" => "author", "type" => "text", "default" => "");
        $options[] = array("name" => "title", "type" => "text", "default" => "");
        $options[] = array("name" => "printermarks", "type" => "bool", "default" => "");
        $options[] = array("name" => "screenResolutionImages", "type" => "bool", "default" => false);
        $options[] = array("name" => "links", "type" => "bool", "default" => true);
        $options[] = array("name" => "bookmarks", "type" => "bool", "default" => true);
        $options[] = array("name" => "tags", "type" => "bool", "default" => true);
        $options[] = array(
            "name" => "prJavaScriptMode",
            "type" => "select",
            "values" => array(\JavaScriptMode::ENABLED, \JavaScriptMode::DISABLED, \JavaScriptMode::ENABLED_NO_LAYOUT),
            "default" => \JavaScriptMode::ENABLED
        );

        $options[] = array(
            "name" => "prViewerPreference",
            "type" => "select",
            "values" => array(\ViewerPreferences::PAGE_LAYOUT_SINGLE_PAGE, \ViewerPreferences::PAGE_LAYOUT_TWO_COLUMN_LEFT, \ViewerPreferences::PAGE_LAYOUT_TWO_COLUMN_RIGHT),
            "default" => \ViewerPreferences::PAGE_LAYOUT_SINGLE_PAGE
        );

        $options[] = array(
            "name" => "prColorspace",
            "type" => "select",
            "values" => array(\ColorSpace::CMYK, \ColorSpace::RGB),
            "default" => \ColorSpace::CMYK
        );

        $options[] = array(
            "name" => "prEncryption",
            "type" => "select",
            "values" => array(\Encryption::NONE, \Encryption::TYPE_40, \Encryption::TYPE_128),
            "default" => \Encryption::NONE
        );

        $options[] = array(
            "name" => "prLoglevel",
            "type" => "select",
            "values" => array(\LogLevel::FATAL, \LogLevel::WARN, \LogLevel::INFO, \LogLevel::DEBUG, \LogLevel::PERFORMANCE),
            "default" => \LogLevel::FATAL
        );

        return $options;
    }

}