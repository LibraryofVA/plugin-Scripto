<?php
/**
 * Omeka adapter for Scripto.
 */
class ScriptoAdapterOmeka implements Scripto_Adapter_Interface
{
    /**
     * @var Omeka_Db
     */
    private $_db;
    
    /**
     * Set the database object on construction.
     */
    public function __construct()
    {
        $this->_db = get_db();
    }
    
    /**
     * Indicate whether the document exists in Omeka.
     * 
     * @param int|string $documentId The unique document ID
     * @return bool True: it exists; false: it does not exist
     */
    public function documentExists($documentId)
    {
        return $this->_validDocument($this->_getItem($documentId));
    }
    
    /**
     * Indicate whether the document page exists in Omeka.
     * 
     * @param int|string $documentId The unique document ID
     * @param int|string $pageId The unique page ID
     * @return bool True: it exists; false: it does not exist
     */
    public function documentPageExists($documentId, $pageId)
    {
        $item = $this->_getItem($documentId);
        if (false == $this->_validDocument($item)) {
            return false;
        }
        // The Omeka file ID must match the Scripto page ID.
        $files = $item->Files;
        foreach ($files as $file) {
            if ($pageId == $file->id) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get all the pages belonging to the document.
     * 
     * @param int|string $documentId The unique document ID
     * @return array An array containing page identifiers as keys and page names 
     * as values, in sequential page order.
     */
    public function getDocumentPages($documentId)
    {
        $item = $this->_getItem($documentId);
        $documentPages = array();
        foreach ($item->Files as $file) {
            // The page name is either the Dublin Core title of the file or the 
            // file's original filename.
            $titles = $file->getElementTextsByElementNameAndSetName('Title', 'Dublin Core');
            if (empty($titles)) {
                $pageName = $file->original_filename;
            } else {
                $pageName = $titles[0]->text;
            }
            $documentPages[$file->id] = $pageName;
        }
        return $documentPages;
    }
    
    /**
     * Get the URL of the specified document page file.
     * 
     * @param int|string $documentId The unique document ID
     * @param int|string $pageId The unique page ID
     * @return string The page file URL
     */
    public function getDocumentPageFileUrl($documentId, $pageId)
    {
        $file = $this->_getFile($pageId);
        return $file->getWebPath('archive');
    }
    
    /**
     * Get the first page of the document.
     * 
     * @param int|string $documentId The document ID
     * @return int|string
     */
    public function getDocumentFirstPageId($documentId)
    {
        $item = $this->_getItem($documentId);
        return $item->Files[0]->id;
    }
    
    /**
     * Get the title of the document.
     * 
     * @param int|string $documentId The document ID
     * @return string
     */
    public function getDocumentTitle($documentId)
    {
        $item = $this->_getItem($documentId);
        $titles = $item->getElementTextsByElementNameAndSetName('Title', 'Dublin Core');
        if (empty($titles)) {
            return '';
        }
        return $titles[0]->text;
    }
    
    /**
     * Get the name of the document page.
     * 
     * @param int|string $documentId The document ID
     * @param int|string $pageId The unique page ID
     * @return string
     */
    public function getDocumentPageName($documentId, $pageId)
    {
        $file = $this->_getFile($pageId);
        
        // The page name is either the Dublin Core title of the file or the 
        // file's original filename.
        $titles = $file->getElementTextsByElementNameAndSetName('Title', 'Dublin Core');
        if (empty($titles)) {
            $pageName = $file->original_filename;
        } else {
            $pageName = $titles[0]->text;
        }
        return $pageName;
    }

    /**
    * Get the existing document page transcription if it already exists
    * @param int|string $pageId The unique page ID
    * @return string
    */
    public function getDocumentPageTranscription($pageId)
    {
        $file = $this->_getFile($pageId);
        
        // The transcription text comes from the Scripto transcription field of the file. 
        // If no existing transcription, then return null.
        $transcription = $file->getElementTextsByElementNameAndSetName('Transcription', 'Scripto');
        if (empty($transcription)) {
            $pageText = null;
        } else {
            $pageText = $transcription[0]->text;
        }
        return $pageText;
    }

    /**
     * Indicate whether the document transcription has been imported.
     * 
     * @param int|string $documentId The document ID
     * @return bool True: has been imported; false: has not been imported
     */
    public function documentTranscriptionIsImported($documentId)
    {}
    
    /**
     * Indicate whether the document page transcription has been imported.
     * 
     * @param int|string $documentId The document ID
     * @param int|string $pageId The page ID
     */
    public function documentPageTranscriptionIsImported($documentId, $pageId)
    {}
    
    /**
     * Import a document page's transcription into Omeka.
     * 
     * @param int|string $documentId The document ID
     * @param int|string $pageId The page ID
     * @param string $text The text to import
     * @return bool True: success; false: fail
     */
    public function importDocumentPageTranscription($documentId, $pageId, $text)
    {
        $file = $this->_getFile($pageId);
        $element = $file->getElementByNameAndSetName('Transcription', 'Scripto');
        $file->deleteElementTextsByElementId(array($element->id));
        $isHtml = false;
        if ('html' == get_option('scripto_import_type')) {
            $isHtml = true;
        }
        $text = Scripto::removeNewPPLimitReports($text);
        $file->addTextForElement($element, $text, $isHtml);
        $file->save();
    }
    
    /**
     * Import an entire document's transcription into Omeka.
     * 
     * @param int|string The document ID
     * @param string The text to import
     * @return bool True: success; false: fail
     */
    public function importDocumentTranscription($documentId, $text)
    {
        $item = $this->_getItem($documentId);
        $element = $item->getElementByNameAndSetName('Transcription', 'Scripto');
        $item->deleteElementTextsByElementId(array($element->id));
        $isHtml = false;
        if ('html' == get_option('scripto_import_type')) {
            $isHtml = true;
        }
        $text = Scripto::removeNewPPLimitReports($text);
        $item->addTextForElement($element, $text, $isHtml);
        $item->save();
    }

    /**
     * Check the transcription status of a document page in the Omeka database.
     * @param int|string $documentId The documentID
     * @param int|string $pageId The page ID
     * @return string
     */
    public function documentPageTranscriptionStatus($pageId)
    {
        $file = $this->_getFile($pageId);
        $elementTexts = $file->getElementTextsByElementNameAndSetName('Status', 'Scripto');
        foreach ($elementTexts as $elementText) {
            $status = $elementText->text;
        }
        if (empty($status)) {
            $status = 'Not Started';
            return $status;
        } else {
            return $status;
        }
    }

    /**
     * Set a page transcription status in Omeka.
     * 
     * @param int|string $documentId The document ID
     * @param int|string $pageId The page ID
     * @param int|string $status The page transcription status
     */
    public function importPageTranscriptionStatus($documentId, $pageId, $status)
    {
        //delete current transcription status
        $file = $this->_getFile($pageId);
        $element = $file->getElementByNameAndSetName('Status', 'Scripto');
        $file->deleteElementTextsByElementId(array($element->id));
        //save status to Omeka
        $file->addTextForElement($element, $status);
        $file->save();
        
    }

    /**
     * Set the document progress (percent transcribed) in Omeka.
     *
     * @param int|string $documentId The document ID
     * @param int|string $progress The document progress
     */
    public function importDocumentTranscriptionProgress($documentId, $completedProgress, $needsReviewProgress)
    {
        //delete current values for Percent Completed and Percent Needs Review
        $item = $this->_getItem($documentId);
        $completed = $item->getElementByNameAndSetName('Percent Completed', 'Scripto');
        $needsReview = $item->getElementByNameAndSetName('Percent Needs Review', 'Scripto');
        $item->deleteElementTextsByElementId(array($completed->id));
        $item->deleteElementTextsByElementId(array($needsReview->id));
        //save progress to Omeka
        if ($completedProgress != '0') {
            $item->addTextForElement($completed, $completedProgress);
        }
        if ($needsReviewProgress != '0') {
            $item->addTextForElement($needsReview, $needsReviewProgress);
        }
        $item->save();
    }

    /**
     * Set the item sort weight in item-level Omeka record ('Audience', 'Dublin Core').
     *
     * @param int|string $documentId The document ID
     * @param int|string $weight The 9 digit sort weight
     */

    public function importItemSortWeight($documentId, $weight)
    {
        //delete current value of item sort weight
        $item = $this->_getItem($documentId);
        $sortWeight = $item->getElementByNameAndSetName('Audience', 'Dublin Core'); 
        $item->deleteElementTextsByElementId(array($sortWeight->id));
        //save sort weight to Omeka
        $item->addTextForElement($sortWeight, $weight);
        $item->save();
    }

    /**
     * Return an Omeka item object.
     * 
     * @param int $itemId
     * @return Item|null
     */
    private function _getItem($itemId)
    {
        return $this->_db->getTable('Item')->find($itemId);
    }
    
    /**
     * Return an Omeka file object.
     * 
     * @param int $fileId
     * @return File|int
     */
    private function _getFile($fileId)
    {
        return $this->_db->getTable('File')->find($fileId);
    }
    
    /**
     * Check if the provided item exists in Omeka and is a valid Scripto 
     * document.
     * 
     * @param Item $item
     * @return bool
     */
    private function _validDocument($item)
    {
        // The item must exist.
        if (!($item instanceof Item)) {
            return false;
        }
        // The item must have at least one file assigned to it.
        if (!isset($item->Files[0])) {
            return false;
        }
        return true;
    }
}
