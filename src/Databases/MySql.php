<?php

namespace Spatie\DbDumper\Databases;

use Spatie\DbDumper\DbDumper;
use Spatie\DbDumper\Exceptions\CannotStartDump;
use Symfony\Component\Process\Process;

class MySql extends DbDumper
{
    protected $dbName;
    protected $userName;
    protected $password;
    protected $host = 'localhost';
    protected $port = 3306;
    protected $socket;
    protected $dumpBinaryPath = '';
    protected $useExtendedInserts = true;

    /**
     * @return string
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * @param string $dbName
     *
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function setDbName($dbName)
    {
        $this->dbName = $dbName;

        return $this;
    }

    /**
     * @param string $userName
     *
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function setUserName($userName)
    {
        $this->userName = $userName;

        return $this;
    }

    /**
     * @param string $password
     *
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @param string $host
     *
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param int $port
     *
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * @param int $socket
     *
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function setSocket($socket)
    {
        $this->socket = $socket;

        return $this;
    }

    /**
     * @param string $dumpBinaryPath
     *
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function setDumpBinaryPath($dumpBinaryPath)
    {
        if ($dumpBinaryPath !== '' && substr($dumpBinaryPath, -1) !== '/') {
            $dumpBinaryPath .= '/';
        }

        $this->dumpBinaryPath = $dumpBinaryPath;

        return $this;
    }

    /**
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function useExtendedInserts()
    {
        $this->useExtendedInserts = true;

        return $this;
    }

    /**
     * @return \Spatie\DbDumper\Databases\MySql
     */
    public function dontUseExtendedInserts()
    {
        $this->useExtendedInserts = false;

        return $this;
    }

    /**
     * Dump the contents of the database to the given file.
     *
     * @param string $dumpFile
     *
     * @throws \Spatie\DbDumper\Exceptions\CannotStartDump
     * @throws \Spatie\DbDumper\Exceptions\DumpFailed
     */
    public function dumpToFile($dumpFile)
    {
        $this->guardAgainstIncompleteCredentials();

        $tempFileHandle = tmpfile();

        fwrite($tempFileHandle, $this->getContentsOfCredentialsFile());

        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];

        $command = $this->getDumpCommand($dumpFile, $temporaryCredentialsFile);

        $process = new Process($command);

        $process->run();

        $this->checkIfDumpWasSuccessFul($process, $dumpFile);
    }

    /**
     * Get the command that should be performed to dump the database.
     *
     * @param string $dumpFile
     * @param string $temporaryCredentialsFile
     *
     * @return string
     */
    public function getDumpCommand($dumpFile, $temporaryCredentialsFile)
    {
        $command = [
            "{$this->dumpBinaryPath}mysqldump",
            "--defaults-extra-file={$temporaryCredentialsFile}",
            '--skip-comments',
            $this->useExtendedInserts ? '--extended-insert' : '--skip-extended-insert',
        ];

        if ($this->socket != '') {
            $command[] = "--socket={$this->socket}";
        }

        $command[] = "{$this->dbName} > {$dumpFile}";

        return implode(' ', $command);
    }

    public function getContentsOfCredentialsFile() : string
    {
        $contents = [
            '[client]',
            "user = '{$this->userName}'",
            "password = '{$this->password}'",
            "host = '{$this->host}'",
            "port = '{$this->port}'",
        ];

        return implode(PHP_EOL, $contents);
    }

    protected function guardAgainstIncompleteCredentials()
    {
        foreach (['userName', 'dbName', 'host'] as $requiredProperty) {
            if (strlen($this->$requiredProperty) === 0) {
                throw CannotStartDump::emptyParameter($requiredProperty);
            }
        }
    }
}
