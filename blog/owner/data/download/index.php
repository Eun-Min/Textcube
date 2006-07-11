<?
define('ROOT', '../../../..');
require ROOT . '/lib/includeForOwner.php';
if (file_exists(ROOT . "/cache/backup/$owner.xml")) {
	header('Content-Disposition: attachment; filename="Tattertools-Backup-' . Timestamp::getDate(filemtime(ROOT . "/cache/backup/$owner.xml")) . '.xml"');
	header('Content-Description: Tattertools Backup Data');
	header('Content-Transfer-Encoding: binary');
	header('Content-Type: application/xml');
	readfile(ROOT . "/cache/backup/$owner.xml");
} else {
	respondNotFoundPage();
}
?>