<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace tasks;

use Phing\Exception\BuildException;
use Phing\Project;
use Phing\Task\Ext\Ssh\ScpTask;
use RuntimeException;

/**
 * Copy files to and from a remote host using SFTP through cURL or SSH2, depending on what is available.
 */
class CurlSftpTask extends ScpTask
{
	protected $useSSH2 = true;

	public function main()
	{
		$p = $this->getProject();

		$this->useSSH2 = function_exists('ssh2_connect');

		if ($this->file == "" && empty($this->filesets))
		{
			throw new BuildException("Missing either a nested fileset or attribute 'file'");
		}

		if ($this->host == "" || $this->username == "")
		{
			throw new BuildException("Attribute 'host' and 'username' must be set");
		}

		if ($this->useSSH2)
		{
			$this->usingSSH2($p);

			return;
		}

		$this->usingCURL($p);
	}

	/**
	 * @param   Project  $p
	 */
	protected function usingCURL($p)
	{
		$methods          = !empty($this->methods) ? $this->methods->toArray($p) : array();

		try
		{
			$this->connect();
		}
		catch (RuntimeException $e)
		{
			throw new BuildException("Could not establish connection to " . $this->host . ":" . $this->port . "!");
		}

		if ($this->file != "")
		{
			$remoteFile = rtrim($this->todir, '/\\') . '/' . basename($this->file);
			$this->upload($this->file, $remoteFile);
		}
		else
		{
			if ($this->fetch)
			{
				throw new BuildException("Unable to use filesets to retrieve files from remote server");
			}

			foreach ($this->filesets as $fs)
			{
				$ds    = $fs->getDirectoryScanner($this->project);
				$files = $ds->getIncludedFiles();
				$dir   = $fs->getDir($this->project)->getPath();
				foreach ($files as $file)
				{
					$path = $dir . DIRECTORY_SEPARATOR . $file;

					// Translate any Windows paths
					$remoteFile = rtrim($this->todir, '/\\') . '/' . strtr($file, '\\', '/');
					$this->upload($path, $remoteFile);
				}
			}
		}

		$this->log(
			"Copied " . $this->counter . " file(s) " . ($this->fetch ? "from" : "to") . " '" . $this->host . "'"
		);
	}

	/**
	 * @param   Project  $p
	 */
	protected function usingSSH2($p)
	{
		$methods          = !empty($this->methods) ? $this->methods->toArray($p) : array();
		$this->connection = ssh2_connect($this->host, $this->port, $methods);
		if (!$this->connection)
		{
			throw new BuildException("Could not establish connection to " . $this->host . ":" . $this->port . "!");
		}

		$could_auth = null;

		if ($this->pubkeyfile)
		{
			$could_auth = ssh2_auth_pubkey_file(
				$this->connection,
				$this->username,
				$this->pubkeyfile,
				$this->privkeyfile,
				$this->privkeyfilepassphrase
			);
		}
		elseif (!empty($this->password))
		{
			$could_auth = ssh2_auth_password($this->connection, $this->username, $this->password);
		}
		else
		{
			$could_auth = ssh2_auth_agent($this->connection, $this->username);
		}


		if (!$could_auth)
		{
			throw new BuildException("Could not authenticate connection!");
		}

		// prepare sftp resource
		if ($this->autocreate)
		{
			$this->sftp = ssh2_sftp($this->connection);
		}

		if ($this->file != "")
		{
			$this->copyFileWithSSH2($this->file, basename($this->file));
		}
		else
		{
			if ($this->fetch)
			{
				throw new BuildException("Unable to use filesets to retrieve files from remote server");
			}

			foreach ($this->filesets as $fs)
			{
				$ds    = $fs->getDirectoryScanner($this->project);
				$files = $ds->getIncludedFiles();
				$dir   = $fs->getDir($this->project)->getPath();
				foreach ($files as $file)
				{
					$path = $dir . DIRECTORY_SEPARATOR . $file;

					// Translate any Windows paths
					$this->copyFileWithSSH2($path, strtr($file, '\\', '/'));
				}
			}
		}

		$this->log(
			"Copied " . $this->counter . " file(s) " . ($this->fetch ? "from" : "to") . " '" . $this->host . "'"
		);

		// explicitly close ssh connection
		@ssh2_exec($this->connection, 'exit');
	}

	/**
	 * @param $local
	 * @param $remote
	 *
	 * @throws BuildException
	 */
	protected function copyFileWithSSH2($local, $remote)
	{
		$path = rtrim($this->todir, "/") . "/";

		if ($this->fetch)
		{
			$localEndpoint  = $path . $remote;
			$remoteEndpoint = $local;

			$this->log('Will fetch ' . $remoteEndpoint . ' to ' . $localEndpoint, $this->logLevel);

			$ret = @ssh2_scp_recv($this->connection, $remoteEndpoint, $localEndpoint);

			if ($ret === false)
			{
				throw new BuildException("Could not fetch remote file '" . $remoteEndpoint . "'");
			}
		}
		else
		{
			$localEndpoint  = $local;
			$remoteEndpoint = $path . $remote;

			if ($this->autocreate)
			{
				ssh2_sftp_mkdir(
					$this->sftp,
					dirname($remoteEndpoint),
					(is_null($this->mode) ? 0777 : $this->mode),
					true
				);
			}

			$this->log('Will copy ' . $localEndpoint . ' to ' . $remoteEndpoint, $this->logLevel);

			$ret = false;
			// If more than "$this->heuristicDecision" successfully send files by "ssh2.sftp" over "ssh2_scp_send"
			// then ship this step (task finish ~40% faster)
			if ($this->heuristicScpSftp < $this->heuristicDecision)
			{
				if (null !== $this->mode)
				{
					$ret = @ssh2_scp_send($this->connection, $localEndpoint, $remoteEndpoint, $this->mode);
				}
				else
				{
					$ret = @ssh2_scp_send($this->connection, $localEndpoint, $remoteEndpoint);
				}
			}

			// sometimes remote server allow only create files via sftp (eg. phpcloud.com)
			if (false === $ret && $this->sftp)
			{
				// mark failure of "scp"
				--$this->heuristicScpSftp;

				// try create file via ssh2.sftp://file wrapper
				$fh = @fopen("ssh2.sftp://$this->sftp/$remoteEndpoint", 'wb');
				if (is_resource($fh))
				{
					$ret = fwrite($fh, file_get_contents($localEndpoint));
					fclose($fh);

					// mark success of "sftp"
					$this->heuristicScpSftp += 2;
				}
			}

			if ($ret === false)
			{
				throw new BuildException("Could not create remote file '" . $remoteEndpoint . "'");
			}
		}

		$this->counter++;
	}

	/**
	 * Returns a cURL resource handler for the remote SFTP server
	 *
	 * @param   string $remoteFile Optional. The remote file / folder on the SFTP server you'll be manipulating with cURL.
	 *
	 * @return  resource
	 */
	protected function getCurlHandle($remoteFile = '')
	{
		// Remember, the username has to be URL encoded as it's part of a URI!
		$authentication = urlencode($this->username);

		// We will only use username and password authentication if there are no certificates configured.
		if (empty($this->pubkeyfile) && !empty($this->password))
		{
			// Remember, both the username and password have to be URL encoded as they're part of a URI!
			$password = urlencode($this->password);
			$authentication .= ':' . $password;
		}

		$ftpUri = 'sftp://' . $authentication . '@' . $this->host;

		if (!empty($this->port))
		{
			$ftpUri .= ':' . (int) $this->port;
		}

		// Relative path? Append the initial directory.
		if (substr($remoteFile, 0, 1) != '/')
		{
			$ftpUri .= $this->todir;
		}

		// Add a remote file if necessary. The filename must be URL encoded since we're creating a URI.
		if (!empty($remoteFile))
		{
			$suffix = '';

			$dirname = dirname($remoteFile);

			// Windows messing up dirname('/'). KILL ME.
			if ($dirname == '\\')
			{
				$dirname = '';
			}

			$dirname = trim($dirname, '/');
			$basename = basename($remoteFile);

			if ((substr($remoteFile, -1) == '/') && !empty($basename))
			{
				$suffix = '/' . $suffix;
			}

			$ftpUri .= '/' . $dirname . (empty($dirname) ? '' : '/') . urlencode($basename) . $suffix;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $ftpUri);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3600);

		// Do I have to use certificate authentication?
		if (!empty($this->pubkeyfile))
		{
			// We always need to provide a public key file
			curl_setopt($ch, CURLOPT_SSH_PUBLIC_KEYFILE, $this->pubkeyfile);

			// Since SSH certificates are self-signed we cannot have cURL verify their signatures against a CA.
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, 0);

			/**
			 * This is optional because newer versions of cURL can extract the private key file from a combined
			 * certificate file.
			 */
			if (!empty($this->privkeyfile))
			{
				curl_setopt($ch, CURLOPT_SSH_PRIVATE_KEYFILE, $this->privkeyfile);
			}

			/**
			 * In case of encrypted (a.k.a. password protected) private key files you need to also specify the
			 * certificate decryption key in the password field. However, if libcurl is compiled against the GnuTLS
			 * library (instead of OpenSSL) this will NOT work because of bugs / missing features in GnuTLS. It's the
			 * same problem you get when libssh is compiled against GnuTLS. The solution to that is having an
			 * unencrypted private key file.
			 */
			if (!empty($this->privkeyfilepassphrase))
			{
				curl_setopt($ch, CURLOPT_KEYPASSWD, $this->privkeyfilepassphrase);
			}
		}
		elseif (empty($this->password))
		{
			curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_AGENT);
		}

		// Should I enable verbose output? Useful for debugging.
		// curl_setopt($ch, CURLOPT_VERBOSE, 1);

		curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS , 1);

		return $ch;
	}

	/**
	 * Test the connection to the SFTP server and whether the initial directory is correct. This is done by attempting to
	 * list the contents of the initial directory. The listing is not parsed (we don't really care!) and we do NOT check
	 * if we can upload files to that remote folder.
	 *
	 * @throws  RuntimeException
	 */
	protected function connect()
	{
		$ch = $this->getCurlHandle($this->todir . '/');
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$listing = curl_exec($ch);
		$errNo   = curl_errno($ch);
		$error   = curl_error($ch);
		curl_close($ch);

		if ($errNo)
		{
			throw new RuntimeException("cURL Error $errNo connecting to remote SFTP server: $error", 500);
		}
	}

	/**
	 * Uploads a local file to the remote storage
	 *
	 * @param   string  $localFilename   The full path to the local file
	 * @param   string  $remoteFilename  The full path to the remote file
	 *
	 * @return  boolean  True on success
	 */
	public function upload($localFilename, $remoteFilename)
	{
		$fp = @fopen($localFilename, 'rb');

		if ($fp === false)
		{
			throw new RuntimeException("Unreadable local file $localFilename");
		}

		// Note: don't manually close the file pointer, it's closed automatically by uploadFromHandle
		try
		{
			$this->uploadFromHandle($remoteFilename, $fp);
		}
		catch (RuntimeException $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Uploads a file using file contents provided through a file handle
	 *
	 * @param   string   $remoteFilename
	 * @param   resource $fp
	 *
	 * @return  void
	 *
	 * @throws  RuntimeException
	 */
	protected function uploadFromHandle($remoteFilename, $fp)
	{
		// We need the file size. We can do that by getting the file position at EOF
		fseek($fp, 0, SEEK_END);
		$filesize = ftell($fp);
		rewind($fp);

		$ch = $this->getCurlHandle($remoteFilename);
		curl_setopt($ch, CURLOPT_UPLOAD, 1);
		curl_setopt($ch, CURLOPT_INFILE, $fp);
		curl_setopt($ch, CURLOPT_INFILESIZE, $filesize);

		curl_exec($ch);

		$error_no = curl_errno($ch);
		$error    = curl_error($ch);

		curl_close($ch);
		fclose($fp);

		if ($error_no)
		{
			throw new RuntimeException($error, $error_no);
		}
	}

}