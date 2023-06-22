<?php
define('GLPI_ROOT', '../../..');
include (GLPI_ROOT . "/inc/includes.php");

Session::checkLoginUser();

$selectedProject = (int)$_POST['selectedProject'];
$ticketId = (int)$_POST['ticketId'];
$ticketName = $_POST['ticketName'];
$ticketContent = $_POST['ticketContent'];
$usersIds = $_POST['usersIds'];

$result = $DB->request('glpi_plugin_gitlab_integration', ['ticket_id' => [$ticketId]]);

if ($result->count() > 0) {
    $DB->update(
        'glpi_plugin_gitlab_integration', [
           'gitlab_project_id'  => $selectedProject
        ], [
           'ticket_id' => $ticketId
        ]
    );

} else {
    $DB->insert(
        'glpi_plugin_gitlab_integration', [
            'ticket_id'         => $ticketId,
            'gitlab_project_id' => $selectedProject
        ]
    );
}

if (class_exists('PluginGitlabIntegrationParameters')) {
    $usersIds = explode(',', $usersIds);
    $fp = fopen(GLPI_ROOT . "/plugins/gitlabintegration/glpiToGitlabUsersIds.txt", "r");
    $usersGitlabIds = [];
    while ($str=fgets($fp)) {
      $str = trim($str);
      if (strlen($str) == 0) continue;
      $fields = preg_split("/[\s]+/", $str);
      $username = $fields[0];
      $userGLPIId = $fields[1];
      $userGitlabId = $fields[2];
      if (in_array($userGLPIId, $usersIds)) $usersGitlabIds[] = $userGitlabId;
    }
    fclose($fp);
    $title = $ticketId . ' - ' . $ticketName;
    $description = "Содержимое заявки см. по ссылке: <a href='" . $_SERVER['HTTP_REFERER'] . "'>$title</a>";
    $description .= "\n\n$ticketContent";
    $description = str_replace('&lt;', '<', $description);
    $description = str_replace('&gt;', '>', $description);
    $description = str_replace('\"', '"', $description);
    $description = str_replace('<br>', "\n", $description);
    $fp = fopen("/tmp/issue.log", 'w');

    fputs($fp, "selectedProject=$selectedProject title=$title, description=$description");
    fputs($fp, "usersIds=".print_r($usersIds, true));
    fputs($fp, "usersGitlabId=" . print_r($usersGitlabIds, true));
    fclose($fp);
    //exit(0);
    PluginGitlabIntegrationGitlabIntegration::CreateIssue($selectedProject, $title, $description, $usersGitlabIds);

    PluginGitlabIntegrationEventLog::Log($ticketId, 'ticket', $_SESSION["glpi_currenttime"], 'issue', 4, sprintf(__('%2s created Issue', 'gitlabintegration'), $_SESSION["glpiname"]));

    Session::addMessageAfterRedirect(__('Issue created successfully!', 'gitlabintegration'));
} else {
    Session::addMessageAfterRedirect(__('Problem to create issue. Verify logs for more information!', 'gitlabintegration'));

    $erro = "[" . $_SESSION["glpi_currenttime"] . "] glpiphplog.ERROR: PluginGitlabIntegrationParameters::create() in issue.php line 34" . PHP_EOL;
    $erro = $erro . "  ***PHP Notice: Class PluginGitlabIntegrationParameters not created";
    PluginGitlabIntegrationEventLog::ErrorLog($erro);
}

