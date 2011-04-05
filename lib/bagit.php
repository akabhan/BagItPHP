<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This is a PHP implementation of the {@link
 * https://wiki.ucop.edu/display/Curation/BagIt BagIt specification}. Really,
 * it is a port of {@link https://github.com/ahankinson/pybagit/ PyBagIt} for
 * PHP.
 *
 * PHP version 5
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy
 * of * the License at http://www.apache.org/licenses/LICENSE-2.0 Unless
 * required by applicable law or agreed to in writing, software distributed
 * under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR
 * CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 *
 * @category  FileUtils
 * @package   Bagit
 * @author    Eric Rochester <erochest@gmail.com>
 * @author    Wayne Graham <wayne.graham@gmail.com>
 * @copyright 2011 The Board and Visitors of the University of Virginia
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache 2.0
 * @version   0.1
 * @link      https://github.com/erochest/BagItPHP
 *
 */


require_once 'Archive/Tar.php';
require_once 'bagit_utils.php';


/**
 * This is a class for all bag exceptions.
 *
 * @category   FileUtils
 * @package    Bagit
 * @subpackage Exception
 * @author     Eric Rochester <erochest@gmail.com>
 * @author     Wayne Graham <wayne.graham@gmail.com>
 * @copyright  2011 The Board and Visitors of the University of Virginia
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache 2.0
 * @version    0.1
 * @link       https://github.com/erochest/BagItPHP
 */
class BagItException extends Exception
{

}


/**
 * This is the main class for interacting with a bag.
 *
 * @category  FileUtils
 * @package   Bagit
 * @author    Eric Rochester <erochest@gmail.com>
 * @author    Wayne Graham <wayne.graham@gmail.com>
 * @copyright 2011 The Board and Visitors of the University of Virginia
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache 2.0
 * @version   0.1
 * @link      https://github.com/erochest/BagItPHP
 */
class BagIt
{

    //{{{ properties

    /**
     * The bag as passed into the constructor. This could be a directory or a
     * file name, and it may not exist.
     *
     * @var string
     */
    var $bag;

    /**
     * Absolute path to the bag directory.
     *
     * @var string
     */
    var $bagDirectory;

    /**
     * True if the bag is extended.
     *
     * @var boolean
     */
    var $extended;

    /**
     * 'sha1' or 'md5'. Default is 'sha1'.
     *
     * @var string
     */
    var $hashEncoding;

    /**
     * The major version number declared in 'bagit.txt'. Default is '0'.
     *
     * @var string
     */
    var $bagMajorVersion;

    /**
     * The minor version number declared in 'bagit.txt'. Default is '96'.
     *
     * @var string
     */
    var $bagMinorVersion;

    /**
     * The tag file encoding declared in 'bagit.txt'. Default is 'utf-8'.
     *
     * @var string
     */
    var $tagFileEncoding;

    /**
     * Absolute path to the data directory.
     *
     * @var string
     */
    var $dataDirectory;

    /**
     * Absolute path to the bagit file.
     *
     * @var string
     */
    var $bagitFile;

    /**
     * Absolute path to the 'manifest-{sha1,md5}.txt' file.
     *
     * @var string
     */
    var $manifestFile;

    /**
     * Absolute path to the 'tagmanifest-{sha1,md5}.txt' file or null.
     *
     * @var string
     */
    var $tagManifestFile;

    /**
     * Absolute path to the 'fetch.txt' file or null.
     *
     * @var string
     */
    var $fetchFile;

    /**
     * Absolute path to the 'bag-info.txt' file or null.
     *
     * @var string
     */
    var $bagInfoFile;

    /**
     * A dictionary array containing the manifest file contents.
     *
     * @var array
     */
    var $manifestData;

    /**
     * A dictionary array containing the tagmanifest file contents.
     *
     * @var array
     */
    var $tagManifestData;

    /**
     * A dictionary array containing the 'fetch.txt' file contents.
     *
     * @var array
     */
    var $fetchData;

    /**
     * A dictionary array containing the 'bag-info.txt' file contents.
     *
     * @var array
     */
    var $bagInfoData;

    /**
     * If the bag came from a compressed file, this contains either 'tgz' or
     * 'zip' to indicate the file's compression format.
     *
     * @var string
     */
    var $bagCompression;

    /**
     * An array of all bag validation errors. Each entries is a two-element
     * array containing the path of the file and the error message.
     *
     * @var array
     */
    var $bagErrors;

    //}}}

    //{{{ Public Methods

    /**
     * Define a new BagIt instance.
     *
     * @param string  $bag      Either a non-existing folder name (will create
     * a new bag here); an existing folder name (this will treat it as a bag
     * and create any missing files or folders needed); or an existing
     * compressed file (this will un-compress it to a temporary directory and
     * treat it as a bag).
     * @param boolean $validate This will validate all files in the bag,
     * including running checksums on all of them. Default is false.
     * @param boolean $extended This will ensure that optional 'bag-info.txt',
     * 'fetch.txt', and 'tagmanifest-{sha1,md5}.txt' are created. Default is
     * true.
     * @param boolean $fetch    If true, it will download all files in
     * 'fetch.txt'. Default is false.
     */
    public function BagIt($bag, $validate=false, $extended=true, $fetch=false)
    {
        $this->bag = $bag;
        $this->extended = $extended;
        $this->hashEncoding = 'sha1';
        $this->bagMajorVersion = 0;
        $this->bagMinorVersion = 96;
        $this->tagFileEncoding = 'UTF-8';
        $this->dataDirectory = null;
        $this->bagDirectory = null;
        $this->bagitFile = null;
        $this->manifestFile = null;
        $this->tagManifestFile = null;
        $this->fetchFile = null;
        $this->bagInfoFile = null;
        $this->manifestData = null;
        $this->tagManifestData = null;
        $this->fetchData = null;
        $this->bagInfoData = null;
        $this->bagCompression = null;
        $this->bagErrors = array();

        if (file_exists($this->bag))
        {
            $this->openBag();
        }
        else
        {
            $this->createBag();
        }

        if ($fetch)
        {
            $this->fetch();
        }

        if ($validate)
        {
            $this->validate();
        }
    }

    /**
     * Test if a Bag is valid
     *
     * @return boolean True if no validation errors occurred.
     */
    public function isValid()
    {
        return (count($this->bagErrors) == 0);
    }

    /**
     * Test if a bag has optional files
     *
     * @return boolean True if the bag contains the optional files
     * 'bag-info.txt', 'fetch.txt', or 'tagmanifest-{sha1,md5}.txt'.
     */
    function isExtended()
    {
        return $this->extended;
    }

    /**
     * Return the info keys
     *
     * @return array A dictionary array containing these keys: 'version',
     * 'encoding', 'hash'.
     */
    function getBagInfo()
    {
        $info = array(
            'version'  => "{$this->bagMajorVersion}.{$this->bagMinorVersion}",
            'encoding' => $this->tagFileEncoding,
            'hash'     => $this->hashEncoding
        );
        return $info;
    }

    /**
     * Get the absolute path of the bag's data directory
     *
     * @return string The absolute path to the bag's data directory.
     */
    function getDataDirectory()
    {
        return $this->dataDirectory;
    }

    /**
     * Determine hash encoding
     *
     * @return string The bag's checksum encoding scheme.
     */
    function getHashEncoding()
    {
        return $this->hashEncoding;
    }

    /**
     * Sets the bag's checksum hash algorithm.
     *
     * @param string $hashAlgorithm The bag's checksum hash algorithm. Must be
     * either 'sha1' or 'md5'.
     *
     * @return void
     */
    function setHashEncoding($hashAlgorithm)
    {
        $hashAlgorithm = strtolower($hashAlgorithm);
        if ($hashAlgorithm != 'md5' && $hashAlgorithm != 'sha1')
        {
            throw new Exception("Invalid hash algorithim: '$hashAlgorithm'.");
        }

        $this->hashEncoding = $hashAlgorithm;
        $this->manifestFile = $this->bagDirectory .
            '/manifest-' . $hashAlgorithm . '.txt';
        if ($this->tagManifestFile != null)
        {
            $this->tagManifestFile = $this->bagDirectory .
                '/tagManifest-' . $hashAlgorithm . '.txt';
        }
    }

    /**
     * Return an array of all files in the data directory
     *
     * @return array An array of absolute paths for all of the files in the
     * data directory.
     */
    function getBagContents() {
        return rls($this->dataDirectory);
    }

    /**
     * Return errors for a bag
     *
     * @param boolean $validate If true, then it will run this->validate() to
     * verify the integrity first. Default is false.
     *
     * @return array An array of all bag errors.
     */
    function getBagErrors($validate=false)
    {
        if ($validate)
        {
            $this->validate();
        }
        return $this->bagErrors;
    }

    /**
     * Runs the bag validator on the contents of the bag. This verifies the
     * presence of required iles and folders and verifies the checksum for
     * each file.
     *
     * For the results of validation, check isValid() and getBagErrors().
     *
     * @return array The list of bag errors.
     */
    function validate()
    {
        $errors = array();

        $this->validateExists($this->bagitFile, $errors);
        $this->validateExists($this->dataDirectory, $errors);
        $this->validateExists($this->manifestFile, $errors);

        if (is_dir($this->dataDirectory) && file_exists($this->manifestFile))
        {
            foreach ($this->getBagContents() as $filename)
            {
                $this->validateChecksum($filename, $errors);
            }

        }
        else
        {
            array_push(
                $errors,
                array('checksum verification', 'Unable to verify manifest.')
            );
        }

        $this->bagErrors = $errors;
        return $this->bagErrors;
    }

    /**
     * This method is used whenever something is added to or removed from the
     * bag. It performs these steps:
     *
     * <ul>
     * <li>Ensures that required files are present;</li>
     * <li>Sanitizes file names;</li>
     * <li>Makes sure that checksums are up-to-date;</li>
     * <li>Adds checksums and file entries for new files;</li>
     * <li>Removes checksums and file entries for missing files; and</li>
     * <li>If it's an extended bag, makes sure that those files are also
     * up-to-date.</li>
     * </ul>
     *
     * @return void
     */
    function update()
    {
        $this->updateManifestFileNames();

        $this->clearManifests();
        $this->cleanDataFileNames();

        // Update data file checksums.
        $dataFiles = rls($this->dataDirectory);
        $this->manifestData = $this->updateManifest(
            $dataFiles,
            $this->manifestFile
        );

        // Update meta-file checksums.
        $bagdir = $this->bagDirectory;
        $tagFiles = array(
            "$bagdir/bagit.txt",
            "$bagdir/bag-info.txt",
            "$bagdir/fetch.txt",
            $this->manifestFile
        );
        $this->tagManifestData = $this->updateManifest(
            $tagFiles,
            $this->tagManifestFile
        );
    }

    /**
     * Downloads every entry in 'fetch.txt'.
     *
     * @param boolean $validate If true, then it also calls update() and
     * validate().
     *
     * @return void
     */
    function fetch($validate=false)
    {
        foreach ($this->fetchData as $fetch)
        {
            $filename = $this->bagDirectory . '/' . $fetch['filename'];
            if (! file_exists($filename))
            {
                $this->fetchFile($fetch['url'], $filename);
            }
        }

        if ($validate)
        {
            $this->update();
            $this->validate();
        }
    }

    /**
     * This clears the fetch data.
     *
     * @return void
     */
    function clearFetch()
    {
        $this->fetchData = array();
        $this->writeFile($this->fetchFile, '');
    }

    /**
     * This adds an entry to the fetch data.
     *
     * @param string $url       This is the URL to load the file from.
     * @param string $filename  This is the file name, relative to the bag
     * directory, to save the data to.
     *
     * @return void
     */
    function addFetch($url, $filename)
    {
        array_push(
            $this->fetchData,
            array('url' => $url, 'length' => '-', 'filename' => $filename)
        );
        $this->writeFetch();
    }

    /**
     * Compresses the bag into a file.
     *
     * @param string $destination The file to put the bag into.
     * @param string $method      Either 'tgz' or 'zip'. Default is 'tgz'.
     *
     * @return void
     */
    function package($destination, $method='tgz')
    {
        $method = strtolower($method);
        if ($method != 'zip' && $method != 'tgz')
        {
            throw new BagItException("Invalid compression method: '$method'.");
        }

        if (substr_compare($destination, ".$method", -4, 4, true) != 0)
        {
            $destination = "$destination.$method";
        }

        $package = $this->compressBag($method);
        rename($package, $destination);
    }
    //}}}

    //{{{ Private Methods


    /**
     * This fetches a single file.
     *
     * On errors, this adds an entry to bagErrors.
     *
     * @param string $url       The URL to fetch.
     * @param string $filename  The file name to save to.
     *
     * @return void
     */
    private function fetchFile($url, $filename)
    {
        $dirname = dirname($filename);
        if (! is_dir($dirname))
        {
            mkdir($dirname, 0777, true);
        }

        try
        {
            save_url($url, $filename);
        }
        catch (Exception $e)
        {
            array_push(
                $this->bagErrors,
                array('fetch', "URL $url could down be downloaded.")
            );
            if (file_exists($filename))
            {
                unlink($filename);
            }
        }
    }

    /**
     * This cleans up the manifest files.
     *
     * @return void
     */
    private function clearManifests()
    {
        $basenames = array(
            'manifest-sha1.txt',
            'manifest-md5.txt',
            'tagmanifest-sha1.txt',
            'tagmanifest-md5.txt'
        );

        foreach ($basenames as $basename)
        {
            $fullname = "{$this->bagDirectory}/$basename";
            if (file_exists($fullname))
            {
                unlink($fullname);
            }
        }

        $this->manifestData = array();
        $this->tagManifestData = array();
    }

    /**
     * This is a facade method that takes a list of files, generates a checksum
     * array, and writes it to a file before returning it.
     *
     * @param array $files      The list of absolute file names to generate
     * checksums for.
     * @param string $filename  The name of the file to write the checksums out
     * to.
     *
     * @return array An array mapping relative file names to checksum hashes.
     */
    private function updateManifest($files, $filename)
    {
        $csums = $this->makeChecksumArray($files);
        $this->writeChecksumArray($csums, $filename);
        return $csums;
    }

    /**
     * This cleans up the file names of all the files in the data/ directory.
     *
     * @return void
     */
    private function cleanDataFileNames()
    {
        $dataFiles = rls($this->dataDirectory);
        foreach ($dataFiles as $dataFile)
        {
            $baseName = basename($dataFile);
            if ($baseName == '.' || $baseName == '..')
            {
                continue;
            }

            $cleanName = $this->sanitizeFileName($baseName);
            if ($cleanName === null)
            {
                unlink($dataFile);
            }
            else if ($baseName != $cleanName)
            {
                $dirName = dirname($dataFile);
                rename($dataFile, "$dirName/$cleanName");
            }
        }
    }

    /**
     * This validates that a file or directory exists.
     *
     * @param string $filename The file name to check for.
     * @param array $errors    The list of errors to add the message to, if the
     * file doesn't exist.
     *
     * @return boolean True if the file does exist; false otherwise.
     */
    private function validateExists($filename, &$errors)
    {
        if (! file_exists($filename))
        {
            $basename = basename($filename);
            array_push(
                $errors,
                array($basename, "$basename does not exist.")
            );
            return false;
        }
        return true;
    }

    /**
     * This validates a file's checksum.
     *
     * @param string $filename   The complete filename to check.
     * the name of the file relative to the bag directory.
     * @param errors             The list of errors to add messages to, if the
     * file doesn't validate.
     *
     * @return void
     */
    private function validateChecksum($filename, &$errors)
    {
        $relname = $this->makeRelative($filename);
        $expected = $this->manifestData[$relname];
        $actual = $this->calculateChecksum($filename);

        if ($expected === null)
        {
            array_push(
                $errors,
                array($relname, 'File missing from manifest.')
            );
        }
        else if ($expected != $actual)
        {
            array_push($errors, array($relname, 'Checksum mismatch.'));
        }
    }

    /**
     * Read the data in the file and convert it from tagFileEncoding into
     * UTF-8.
     *
     * @param string $filename The name of the file to read.
     *
     * @return string The contents of the file as UTF-8.
     */
    private function readFile($filename)
    {
        $data = iconv(
            $this->tagFileEncoding,
            'UTF-8',
            file_get_contents($filename)
        );
        return $data;
    }

    /**
     * Write the data in the file, converting it from UTF-8 to tagFileEncoding.
     *
     * @param string $filename The name of the file to write to.
     * @param string $data     The data to write.
     *
     * @return void
     */
    private function writeFile($filename, $data)
    {
        file_put_contents(
            $filename,
            iconv('UTF-8', $this->tagFileEncoding, $data)
        );
    }

    /**
     * Read the data in the file and convert it from tagFileEncoding into
     * UTF-8, then split the lines.
     *
     * @param string $filename The name of the file to read.
     *
     * @return array The lines in the file.
     */
    private function readLines($filename)
    {
        $data = $this->readFile($filename);
        $lines = preg_split('/[\n\r]+/', $data, null, PREG_SPLIT_NO_EMPTY);
        return $lines;
    }

    /**
     * Open an existing bag. This expects $bag to be set.
     *
     * @return void
     */
    private function openBag()
    {
        $this->bagDirectory = ($this->isCompressed()) ?
            $this->getCompressedBaseName($this->bag) :
            $this->bagDirectory = realpath($this->bag);

        $this->readBagIt($this->bagDirectory . '/bagit.txt');

        $ls = scandir($this->bagDirectory);
        if (count($ls) > 0)
        {
            $this->dataDirectory = "{$this->bagDirectory}/data";

            // Read manifest file.
            $manifestData = $this->openManifest(
                array('manifest-sha1.txt', 'manifest-md5.txt')
            );
            if ($manifestData !== null)
            {
                list($filename, $hashEncoding, $manifest) = $manifestData;
                $this->manifestFile = $filename;
                if ($hashEncoding !== null)
                {
                    $this->hashEncoding = $hashEncoding;
                }
                $this->manifestData = $manifest;
            }

            // Read tag manifest file.
            $manifestData = $this->openManifest(
                array('tagmanifest-sha1.txt', 'tagmanifest-md5.txt')
            );
            if ($manifestData !== null)
            {
                list($filename, $hashEncoding, $manifest) = $manifestData;
                $this->tagManifestFile = $filename;
                $this->tagManifestData = $manifest;
            }

            $this->readFetch("{$this->bagDirectory}/fetch.txt");
            $this->readBagInfo("{$this->bagDirectory}/bag-info.txt");
        }
    }

    /**
     * This returns the base name of a compressed bag.
     *
     * @param string $bag The full bag name.
     *
     * @return string The bag name without the compressed-file extension. This
     * is the bag directory.
     */
    private function getCompressedBaseName($bag)
    {
        $matches = array();
        $success = preg_match(
            '/^(.*)\.(zip|tar\.gz|tgz)$/',
            basename($bag),
            $matches
        );
        if ($success)
        {
            $base = $matches[1];
            return $this->uncompressBag($base);
        }
        else
        {
            throw new BagItException(
                "Invalid compressed bag name: $bag."
            );
        }
    }

    /**
     * Create a new bag. This expects $bag to be set.
     *
     * @return void
     */
    private function createBag()
    {
        $cwd = getcwd();

        mkdir($this->bag);
        $this->bagDirectory = realpath($this->bag);

        $this->dataDirectory = $this->bagDirectory . '/data';
        mkdir($this->dataDirectory);

        $this->bagitFile = $this->bagDirectory . '/bagit.txt';
        $this->manifestFile = $this->bagDirectory .
            "/manifest-{$this->hashEncoding}.txt";

        $bagItData =
            "BagIt-Version: " .
            "{$this->bagMajorVersion}.{$this->bagMinorVersion}\n" .
            "Tag-File-Character-Encoding: {$this->tagFileEncoding}\n";
        $this->writeFile($this->bagitFile, $bagItData);

        touch($this->manifestFile);
        $this->manifestData = array();

        $this->createExtendedBag();
    }

    /**
     * This creates the files for an extended bag.
     */
    private function createExtendedBag()
    {
        if ($this->extended)
        {
            $this->tagManifestFile = $this->bagDirectory .
                "/tagmanifest-{$this->hashEncoding}.txt";

            touch($this->tagManifestFile);
            $this->tagManifestData = array();

            $this->fetchFile = $this->bagDirectory . '/fetch.txt';
            touch($this->fetchFile);
            $this->fetchData = array();

            $this->bagInfoFile = $this->bagDirectory . '/bag-info.txt';
            touch($this->bagInfoFile);
            $this->bagInfoData = array();
        }
    }

    /**
     * This takes a file name and makes it relative to the bag directory.
     *
     * This is unsafe, strictly speaking, because it doesn't check that the
     * file name passed in is in fact under the bag directory.
     *
     * @param string $filename An absolute file name under the bag directory.
     *
     * @return string The file name relative to the bag directory.
     */
    private function makeRelative($filename)
    {
        return substr($filename, strlen($this->bagDirectory) + 1);
    }

    /**
     * This takes a list of files and generates an array mapping the file names
     * (made relative to the data directory) to the hashes.
     *
     * @param array $files A list of absolute file names to generate hashes
     * for.
     *
     * @return array A mapping of relative file names to hashes.
     */
    private function makeChecksumArray($files)
    {
        $csums = array();

        foreach ($files as $file)
        {
            if (file_exists($file))
            {
                $hash = $this->calculateChecksum($file);
                $csums[$this->makeRelative($file)] = $hash;
            }
        }

        return $csums;
    }

    /**
     * This writes a checksum array to a file.
     *
     * @param array $csums     The checksum array to write.
     * @param string $filename The name of the file to write the checksums to.
     *
     * @return void
     */
    private function writeChecksumArray($csums, $filename)
    {
        ksort($csums);
        $output = array();

        foreach ($csums as $path => $hash)
        {
            array_push($output, "$hash $path\n");
        }

        $this->writeFile($filename, implode('', $output));
    }

    /**
     * Create the checksum for a file.
     *
     * @param string $filename The file to generate a checksum for.
     *
     * @return string The checksum.
     */
    private function calculateChecksum($filename)
    {
        return hash_file($this->hashEncoding, $filename);
    }

    /**
     * This reads the fetch.txt file into an array list.
     *
     * This sets $this->fetchData to a sequential array of arrays with the
     * keys 'url', 'length', and 'filename'.
     *
     * @param string $filename  If given, this tests whether the file exists,
     * and if it does, it sets the fetchFile parameter before reading the file.
     * If it is set but doesn't exist, then the method returns without reading
     * anything.
     *
     * @return void
     */
    private function readFetch($filename=null) {
        if ($filename !== null)
        {
            if (file_exists($filename))
            {
                $this->fetchFile = $filename;
            }
            else
            {
                return;
            }
        }

        $lines = $this->readLines($this->fetchFile);
        $fetch = array();

        try
        {
            foreach ($lines as $line)
            {
                $fields = preg_split('/\s+/', $line);
                if (count($fields) == 3)
                {
                    array_push(
                        $fetch,
                        array('url' => $fields[0],
                              'length' => $fields[1],
                              'filename' => $fields[2])
                    );
                }
            }
            $this->fetchData = $fetch;

        }
        catch (Exception $e)
        {
            array_push(
                $this->bagErrors,
                array('fetch', 'Error reading fetch file.')
            );
        }
    }

    /**
     * This writes the data in fetchData into fetchFile.
     *
     * @return void
     */
    private function writeFetch()
    {
        $lines = array();

        foreach ($this->fetchData as $fetch)
        {
            $data = array($fetch['url'], $fetch['length'], $fetch['filename']);
            array_push($lines, join(' ', $data) . "\n");
        }

        $this->writeFile($this->fetchFile, join('', $lines));
    }

    /**
     * This reads the bag-info.txt file into an array dictionary.
     *
     * @param string $filename  If given, this tests whether the file exists,
     * and if it does, it sets the bagInfoFile parameter before reading the
     * file. If it is set but doesn't exist, then the method returns without
     * reading anything.
     *
     * @return void
     */
    private function readBagInfo($filename=null)
    {
        if ($filename !== null)
        {
            if (file_exists($filename))
            {
                $this->bagInfoFile = $filename;
            }
            else
            {
                return;
            }
        }

        $lines = $this->readLines($this->bagInfoFile);
        $bagInfo = array();

        try
        {
            $prevKey = null;
            foreach ($lines as $line)
            {
                if (count($line) == 0)
                {
                    // Skip.
                }
                else if ($line[0] == ' ' || $line[1] == '\t')
                {
                    // Continued line.
                    $val = $bagInfo[$prevKey] . ' ' . trim($line);
                    $keys = array(
                        $prevKey,
                        strtolower($prevKey),
                        strtoupper($prevKey)
                    );
                    foreach ($keys as $pk)
                    {
                        $bagInfo[$pk] = $val;
                    }
                }
                else
                {
                    list($key, $val) = preg_split('/:\s*/', $line, 2);
                    $val = trim($val);
                    $bagInfo[$key] = $val;
                    $bagInfo[strtolower($key)] = $val;
                    $bagInfo[strtoupper($key)] = $val;
                    $prevKey = $key;
                }
            }

            $this->bagInfoData = $bagInfo;

        }
        catch (Exception $e)
        {
            array_push(
                $this->bagErrors,
                array('baginfo', 'Error reading bag info file.')
            );
        }
    }

    /**
     * Tests if a bag is compressed
     *
     * @return True if this is a compressed bag.
     */
    private function isCompressed()
    {
        if (is_dir($this->bag))
        {
            return false;
        }
        else
        {
            $bag = strtolower($this->bag);
            if (endsWith($bag, '.zip'))
            {
                $this->bagCompression = 'zip';
                return true;
            }
            else if (endsWith($bag, '.tar.gz') || endsWith($bag, '.tgz'))
            {
                $this->bagCompression = 'tgz';
                return true;
            }
        }
        return false;
    }

    /**
     * This makes sure that the manifest file names have the correct encoding.
     *
     * @return void
     */
    private function updateManifestFileNames()
    {
        $this->manifestFile = $this->bagDirectory .
            '/manifest-' . $this->hashEncoding . '.txt';
        $this->tagManifestFile = $this->bagDirectory .
            '/tagmanifest-' . $this->hashEncoding . '.txt';
    }

    /**
     * This uncompresses a bag.
     *
     * @param string $bagBase The base name for the Bag It directory.
     *
     * @return The bagDirectory.
     */
    private function uncompressBag($bagBase)
    {
        $dir = tempnam(sys_get_temp_dir(), 'bagit_');
        unlink($dir);
        mkdir($dir, 0700);

        if ($this->bagCompression == 'zip')
        {
            $zip = new ZipArchive();
            $zip->open($this->bag);
            $zip->extractTo($dir);

        }
        else if ($this->bagCompression == 'tgz')
        {
            $tar = new Archive_Tar($this->bag, 'gz');
            $tar->extract($dir);

        }
        else
        {
            throw new BagItException(
                "Invalid bag compression format: {$this->bagCompression}."
            );
        }

        return "$dir/$bagBase";
    }

    /**
     * This compresses the bag into a new file.
     *
     * @param string $method Either 'tgz' or 'zip'. Default is 'tgz'.
     *
     * @return string The file name for the file.
     */
    private function compressBag($method='tgz')
    {
        $output = tempnam(sys_get_temp_dir(), 'bagit_');
        unlink($output);

        $base = basename($this->bagDirectory);
        $stripLen = strlen($this->bagDirectory) - strlen($base);

        if ($method == 'zip')
        {
            $zip = new ZipArchive();
            $zip->open($output, ZIPARCHIVE::CREATE);

            foreach (rls($this->bagDirectory) as $file)
            {
                $zip->addFile($file, substr($file, $stripLen));
            }

            $zip->close();

        }
        else if ($method == 'tgz')
        {
            $tar = new Archive_Tar($output, 'gz');
            $tar->createModify(
                $this->bagDirectory,
                $base,
                $this->bagDirectory
            );

        }

        return $output;
    }

    /**
     * This reads the information from the bag it file.
     *
     * This sets the bagMajorVersion, bagMinorVersion, and tagFileEncoding
     * properties.
     *
     * If it encounters an error, it adds it to bagErrors.
     *
     * @param string $filename  If given, this tests whether the file exists,
     * and if it does, it sets the bagitFile parameter before reading the
     * file. If it is set but doesn't exist, then the method returns without
     * reading anything.
     */
    private function readBagIt($filename=null)
    {
        if ($filename !== null)
        {
            if (file_exists($filename))
            {
                $this->bagitFile = $filename;
            }
            else
            {
                return;
            }
        }

        try
        {
            $this->parseBagIt($this->readFile($filename));
        }
        catch (Exception $e)
        {
            array_push(
                $this->bagErrors,
                array('bagit', 'Error reading the bagit.txt file.')
            );
        }
    }

    /**
     * This parses information from the bagit.txt from the string data read 
     * from that file.
     *
     * @param string $data The data from the bagit.txt file.
     */
    private function parseBagIt($data)
    {
        $versions = $this->parseVersionString($data);
        if ($versions === null)
        {
            throw new Exception();
        }
        $this->bagMajorVersion = $versions[0];
        $this->bagMinorVersion = $versions[1];

        $this->tagFileEncoding = $this->parseEncodingString($data);
    }

    /**
     * This reads a manifest file, checking if it exists first.
     *
     * @param array $filenames A list of file names to read. It reads the first
     * that exists.
     *
     * @return array If none of the files passed in exist, this returns
     * <code>null</code>. Otherwise, it returns an array triple. The first
     * element is the manifest file name that was read in, and the second
     * element is the hash encoding from the file name, and the third
     * element is the manifest data read from the file.
     */
    private function openManifest($filenames)
    {
        foreach ($filenames as $filename)
        {
            $fullname = "{$this->bagDirectory}/$filename";
            if (file_exists($fullname))
            {
                $matches = array();
                $hashEncoding = null;
                if (preg_match('/-(sha1|md5)\.txt$/', $filename, $matches))
                {
                    $hashEncoding = $matches[1];
                }

                $manifest = $this->readManifest($fullname, $hashEncoding);

                return array($fullname, $hashEncoding, $manifest);
            }
        }

        return null;
    }

    /**
     * This reads the manifest data from a file.
     *
     * @param string $filename      The file name to read.
     * @param string $hashEncoding  The type of hash encoding used in this
     * file.
     *
     * @return array An array mapping file names (relative to the bag
     * directory) to hashes.
     */
    private function readManifest($filename, $hashEncoding)
    {
        $manifest = array();

        try
        {
            $hashLen = ($hashEncoding == 'sha1') ? 40 : 32;
            $lines = $this->readLines($filename);

            foreach ($lines as $line)
            {
                $hash = trim(substr($line, 0, $hashLen));
                $payload = trim(substr($line, $hashLen));

                if (strlen($payload) > 0)
                {
                    $manifest[$payload] = $hash;
                }
            }

        }
        catch (Exception $e)
        {
            $filename = basename($filename);
            array_push(
                $this->bagErrors,
                array('manifest', "Error reading $filename.")
            );
        }

        return $manifest;
    }

    /**
     * This parses the version string from the bagit.txt file.
     *
     * @param string $bagitFileData The contents of the bagit file.
     *
     * @return array A two-item array containing the version string as
     * integers.
     */
    private function parseVersionString($bagitFileData) {
        $matches = array();
        $success = preg_match(
            "/BagIt-Version: (\d+)\.(\d+)/i",
            $bagitFileData,
            $matches
        );

        if ($success)
        {
            $major = (int)$matches[1];
            $minor = (int)$matches[2];
            if ($major === null || $minor === null)
            {
                throw new Exception("Invalid bagit version: '{$matches[0]}'.");
            }
            return array($major, $minor);
        }

        return null;
    }

    /**
     * This parses the encoding string from the bagit.txt file.
     *
     * @param string $bagitFileData The contents of the bagit file.
     *
     * @return string The encoding.
     */
    private function parseEncodingString($bagitFileData)
    {
        $matches = array();
        $success = preg_match(
            '/Tag-File-Character-Encoding: (.*)/i',
            $bagitFileData,
            $matches
        );

        if ($success)
        {
            return $matches[1];
        }

        return null;
    }

    /**
     * This cleans up the file name.
     *
     * @param string $filename The file name to clean up.
     *
     * @return string The cleaned up file name.
     */
    private function sanitizeFileName($filename)
    {
        // White space => underscores.
        $filename = preg_replace('/\s+/', '_', $filename);

        // Remove some characters.
        $filename = preg_replace(
            '/\.{2}|[~\^@!#%&\*\/:\'?\"<>\|]/',
            '',
            $filename
        );

        $forbidden = '/^(CON|PRN|AUX|NUL|COM1|COM2|COM3|COM4|COM5| ' .
            'COM6|COM7|COM8|COM9|LPT1|LPT2|LPT3|LPT4|LPT5|LPT6|' .
            'LPT7|LPT8|LPT9)$/';

        if (preg_match($forbidden, $filename))
        {
            $filename = strtolower($filename);
            $suffix = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 12);
            $filename = "{$filename}_{$suffix}";
        }

        return $filename;
    }
    //}}}

}

/* Functional wrappers/facades. */

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */


?>
