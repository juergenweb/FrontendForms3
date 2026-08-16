<?php

declare(strict_types=1);

namespace FrontendForms;

/**
 * FileUploadHandler
 *
 * Handles the storage side of file uploads after successful validation:
 * rearranging the raw $_FILES structure for multi-file inputs, moving
 * validated files into the configured upload folder, and tracking the
 * resulting file paths.
 *
 * Note: this class is only responsible for post-validation storage.
 * Validation of uploaded files (allowed extensions, size limits, ZIP
 * content checks, etc.) is handled separately by FileLogic/ZipLogic,
 * which read directly from $_FILES via FileHelper - they do not depend
 * on this class.
 *
 * @package FrontendForms\Upload
 */
final class FileUploadHandler
{
    private string $uploadPath = '';
    private array $uploadedFiles = []; // paths of the files stored during the last storeUploadedFiles() call

    public function __construct(private readonly Form $form)
    {
    }

    /**
     * Set a custom upload path for uploaded files
     * @param string $pathToFolder
     * @return void
     */
    public function setUploadPath(string $pathToFolder): void
    {
        $this->uploadPath = trim($pathToFolder);
    }

    public function getUploadPath(): string
    {
        return $this->uploadPath;
    }

    /**
     * Get all files that were stored during the last storeUploadedFiles() call
     * @return array
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * Overwrite the tracked list of uploaded files directly (used for the legacy
     * "overwritten filenames" compatibility path in Form::___isValid()).
     * @param array $files
     * @return void
     */
    public function setUploadedFiles(array $files): void
    {
        $this->uploadedFiles = $files;
    }

    /**
     * Rearrange the multi-file $_FILES sub-array into one array per file
     * instead of one array per property (name, tmp_name, error, ...).
     * @param array $filePost - the $_FILES[$fieldName] sub-array for a multiple-file input
     * @return array
     */
    public function reArrayFiles(array $filePost): array
    {
        $fileArray = [];
        $fileCount = count($filePost['name']);
        $fileKeys = array_keys($filePost);
        for ($i = 0; $i < $fileCount; $i++) {
            foreach ($fileKeys as $key) {
                $fileArray[$i][$key] = $filePost[$key][$i];
            }
        }
        return $fileArray;
    }

    /**
     * Store all uploaded files from InputFile fields inside the form in the
     * configured upload folder. Remembers the resulting paths internally,
     * retrievable via getUploadedFiles().
     * @param array $formElements
     * @return array the paths of the stored files
     */
    public function storeUploadedFiles(array $formElements): array
    {
        $uploadedFiles = [];
        if ($_FILES) {
            // create directory if it does not exist
            $this->form->wire('files')->mkdir($this->uploadPath);
            // get all upload fields inside the form
            foreach ($formElements as $element) {

                if ($element instanceof InputFile) {
                    $fieldName = $element->getAttribute('name'); // the name of the upload field

                    if ($element->getMultiple()) {
                        // multiple files
                        if (array_key_exists($fieldName, $_FILES)) {
                            $files = $this->reArrayFiles($_FILES[$fieldName]);
                            foreach ($files as $file) {
                                if ($file['error'] == 0) {
                                    // sanitize file name and convert it to lowercase to prevent problems on certain servers
                                    $filename = $this->form->wire('sanitizer')->filename($file['name'], true);
                                    $targetFile = $this->uploadPath . strtolower($filename);
                                    $uploadedFiles[] = $targetFile;
                                    move_uploaded_file($file['tmp_name'], $targetFile);
                                }
                            }
                        }
                    } else {
                        // single file
                        $file = $_FILES[$fieldName];
                        if ($file['error'] == 0) {
                            // sanitize file name and convert it to lowercase to prevent problems on certain servers
                            $filename = $this->form->wire('sanitizer')->filename(basename($file['name']), true);
                            $targetFile = $this->uploadPath . strtolower($filename);
                            $uploadedFiles[] = $targetFile;
                            move_uploaded_file($file['tmp_name'], $targetFile);
                        }
                    }
                }
            }
        }
        $this->uploadedFiles = $uploadedFiles;
        return $uploadedFiles;
    }
}