<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\DataQueries\FileFinderDataQuery;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\DataTypes\FilePathDataType;

class FileFinderConnector extends TransparentConnector
{

    private $base_path = null;

    private $use_vendor_folder_as_base = false;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        $base_path = $this->getBasePath();
        
        if ($this->getUseVendorFolderAsBase() != false) {
            $base_path = $this->getWorkbench()->filemanager()->getPathToVendorFolder();
        } elseif (is_null($base_path)) {
            $base_path = $this->getWorkbench()->filemanager()->getPathToBaseFolder();
        }
        
        if (Filemanager::pathIsAbsolute($this->getBasePath())) {
            $base_path = Filemanager::pathJoin($this->getWorkbench()->filemanager()->getPathToBaseFolder(), $this->getBasePath());
        }
        
        $this->setBasePath($base_path);
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     *
     * @param
     *            FileFinderDataQuery
     * @return FileFinderDataQuery
     */
    protected function performQuery(DataQueryInterface $query)
    {
        if (! ($query instanceof FileFinderDataQuery))
            throw new DataConnectionQueryTypeError($this, 'DataConnector "' . $this->getAliasWithNamespace() . '" expects an instance of FileFinderDataQuery as query, "' . get_class($query) . '" given instead!', '6T5W75J');
        
        $paths = array();
        // Prepare an array of absolut paths to search in
        foreach ($query->getFolders() as $path) {
            if (! Filemanager::pathIsAbsolute($path) && ! is_null($this->getBasePath())) {
                $paths[] = FilePathDataType::normalize(FilePathDataType::join(array(
                    $this->getBasePath(),
                    $path
                )), DIRECTORY_SEPARATOR);
            } else {
                $paths[] = FilePathDataType::normalize($path, DIRECTORY_SEPARATOR);
            }
        }
        
        // If the query does not have a base path, use the base path of the connection
        if (! $query->getBasePath()) {
            $query->setBasePath($this->getBasePath());
        }
        
        // If no paths could be found anywhere (= the query object did not have any folders defined), use the base path
        if (empty($paths)) {
            $paths[] = $query->getBasePath();
        }
        
        // Now doublecheck if all explicit (non-wildcard) paths exists because otherwise
        // finder will throw an error. Just remove all non-existant paths as the definitely
        // do not contain files.
        foreach ($paths as $nr => $path){
            if (strpos($path, '*') === false && ! is_dir($path)){
                unset($paths[$nr]);
            }
        }
        
        // If there are no paths at this point, we don't have any existing folder to look in,
        // so add an empty result to the finder and return it. We must call in() or append()
        // to be able to iterate over the finder!
        if (empty($paths)){
            $query->getFinder()->append([]);
            return $query;
        }
        
        // Make sure not to have double paths as Symfony FileFinder will yield results for each path separately 
        $paths = array_unique($paths);
        // Also try to filter out paths that match paterns in other paths
        $pathsFiltered = $paths;
        foreach ($paths as $path) {
            $path = FilePathDataType::normalize($path);
            if (strpos($path, '*')) {
                foreach ($paths as $i => $otherPath) {
                    $otherPath = FilePathDataType::normalize($otherPath);
                    if ($otherPath !== $path && fnmatch($path, $otherPath)) {
                        unset($pathsFiltered[$i]);
                    }
                }
            }
        }
        
        // Perform the search. This will fill the file and folder iterators in the finder instance. Thus, the resulting
        // files will be available through foreach($query as $splFileInfo) etc.
        try {
            $query->getFinder()->in($pathsFiltered);
        } catch (\Exception $e) {
            throw new DataQueryFailedError($query, "Data query failed!", null, $e);
            return array();
        }
        
        return $query;
    }

    public function getBasePath()
    {
        return $this->base_path;
    }

    /**
     * The base path for relative paths in data addresses.
     * 
     * If a base path is defined, all data addresses will be resolved relative to that path.
     *
     * @uxon-property base_path
     * @uxon-type string
     *
     * @param unknown $value            
     * @return \exface\Core\DataConnectors\FileFinderConnector
     */
    public function setBasePath($value)
    {
        if ($value) {
            $this->base_path = Filemanager::pathNormalize($value, '/');
        } else {
            $this->base_path = '';
        }
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getUseVendorFolderAsBase()
    {
        return $this->use_vendor_folder_as_base;
    }

    /**
     * Set to TRUE to use the current vendor folder as base path.
     * 
     * All data addresses in this conneciton will then be resolved relative to the vendor folder.
     *
     * @uxon-property base_path
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\DataConnectors\FileFinderConnector
     */
    public function setUseVendorFolderAsBase($value)
    {
        $this->use_vendor_folder_as_base = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
}
?>