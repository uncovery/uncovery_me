<?php

function umc_github_link() {
    $secret = 'IY8uSgfq3HWl60jiOzgC';
    // sha1 hash: b89d1eb00d87d281b3aa3c7ddf480524996eb6d2
    //            625b5f28731e107596fd4b0b7464b0eac6a0d35f
    echo "Hello World!";

    $foo = file_get_contents("php://input");

    $value = var_export(json_decode($foo, true), true);
    XMPP_ERROR_trace("$value");

    // header verification:
    // create $event and $D (data) arrays
    extract(umc_github_verify());
    XMPP_ERROR_send_msg($event);
    XMPP_ERROR_send_msg($D);




}

function umc_github_verify() {
    // source: http://isometriks.com/verify-github-webhooks-with-php
    $secret = '[some secret here]';

    $headers = getallheaders();
    if (!isset($headers['X-Hub-Signature'])) {
        die();
    }
    $hubSignature = $headers['X-Hub-Signature'];

    // Split signature into algorithm and hash
    list($algo, $hash) = explode('=', $hubSignature, 2);

    // Get payload
    $payload = file_get_contents('php://input');

    // Calculate hash based on payload and the secret
    $payloadHash = hash_hmac($algo, $payload, $secret);

    // Check if hashes are equivalent
    if ($hash !== $payloadHash) {
        // Kill the script or do something else here.
        die('Bad secret');
    }

    // Your code here.
    $data = json_decode($payload);
    $event = $headers['X-Github-Event'];
    return array('event' => $event, 'D' => $data);
}


/* sample data issue opening
'action' => 'opened',
  'issue' =>
  array (
    'url' => 'https://api.github.com/repos/uncovery/uncovery_me/issues/44',
    'labels_url' => 'https://api.github.com/repos/uncovery/uncovery_me/issues/44/labels{/name}',
    'comments_url' => 'https://api.github.com/repos/uncovery/uncovery_me/issues/44/comments',
    'events_url' => 'https://api.github.com/repos/uncovery/uncovery_me/issues/44/events',
    'html_url' => 'https://github.com/uncovery/uncovery_me/issues/44',
    'id' => 87599527,
    'number' => 44,
    'title' => 'Create a page on the server that lets people see issues updates',
    'user' =>
    array (
      'login' => 'uncovery',
      'id' => 583808,
      'avatar_url' => 'https://avatars.githubusercontent.com/u/583808?v=3',
      'gravatar_id' => '',
      'url' => 'https://api.github.com/users/uncovery',
      'html_url' => 'https://github.com/uncovery',
      'followers_url' => 'https://api.github.com/users/uncovery/followers',
      'following_url' => 'https://api.github.com/users/uncovery/following{/other_user}',
      'gists_url' => 'https://api.github.com/users/uncovery/gists{/gist_id}',
      'starred_url' => 'https://api.github.com/users/uncovery/starred{/owner}{/repo}',
      'subscriptions_url' => 'https://api.github.com/users/uncovery/subscriptions',
      'organizations_url' => 'https://api.github.com/users/uncovery/orgs',
      'repos_url' => 'https://api.github.com/users/uncovery/repos',
      'events_url' => 'https://api.github.com/users/uncovery/events{/privacy}',
      'received_events_url' => 'https://api.github.com/users/uncovery/received_events',
      'type' => 'User',
      'site_admin' => false,
    ),
     'labels' =>
    array (
    ),
    'state' => 'open',
    'locked' => false,
    'assignee' => NULL,
    'milestone' => NULL,
    'comments' => 0,
    'created_at' => '2015-06-12T04:12:59Z',
    'updated_at' => '2015-06-12T04:12:59Z',
    'closed_at' => NULL,
    'body' => 'We need a page on the server where people can follow issues and see what is open and when it closes',
  ),
 *
 * SAMPLE DATA FROM JSON for a Commit
 *
array (
  'ref' => 'refs/heads/master',
  'before' => '4e07dcbb8045d09caeac2c1f653d7c196683ef17',
  'after' => 'ad5ac8d4077045ea5c4b7f18f7d049862107c63b',
  'created' => false,
  'deleted' => false,
  'forced' => false,
  'base_ref' => NULL,
  'compare' => 'https://github.com/uncovery/uncovery_me/compare/4e07dcbb8045...ad5ac8d40770',
  'commits' =>
  array (
    0 =>
    array (
      'id' => 'ad5ac8d4077045ea5c4b7f18f7d049862107c63b',
      'distinct' => true,
      'message' => 'initial commit for github link',
      'timestamp' => '2015-06-12T12:10:41+08:00',
      'url' => 'https://github.com/uncovery/uncovery_me/commit/ad5ac8d4077045ea5c4b7f18f7d049862107c63b',
      'author' =>
      array (
        'name' => 'uncovery',
        'email' => 'minecraft@uncovery.me',
        'username' => 'uncovery',
      ),
      'committer' =>
      array (
        'name' => 'uncovery',
        'email' => 'minecraft@uncovery.me',
        'username' => 'uncovery',
      ),
      'added' =>
      array (
      ),
      'removed' =>
      array (
      ),
      'modified' =>
      array (
        0 => 'includes/github.inc.php',
      ),
    ),
  ),
  'head_commit' =>
  array (
    'id' => 'ad5ac8d4077045ea5c4b7f18f7d049862107c63b',
    'distinct' => true,
    'message' => 'initial commit for github link',
    'timestamp' => '2015-06-12T12:10:41+08:00',
    'url' => 'https://github.com/uncovery/uncovery_me/commit/ad5ac8d4077045ea5c4b7f18f7d049862107c63b',
    'author' =>
    array (
      'name' => 'uncovery',
      'email' => 'minecraft@uncovery.me',
      'username' => 'uncovery',
    ),
    'committer' =>
    array (
      'name' => 'uncovery',
      'email' => 'minecraft@uncovery.me',
      'username' => 'uncovery',
    ),
    'added' =>
    array (
    ),
    'removed' =>
    array (
    ),
    'modified' =>
    array (
      0 => 'includes/github.inc.php',
    ),
  ),
 *
 *
 *
 *
 *
  'repository' =>
  array (
    'id' => 36276258,
    'name' => 'uncovery_me',
    'full_name' => 'uncovery/uncovery_me',
    'owner' =>
    array (
      'name' => 'uncovery',
      'email' => 'minecraft@uncovery.me',
    ),
    'private' => true,
    'html_url' => 'https://github.com/uncovery/uncovery_me',
    'description' => 'Full source of the uncovery.me minecraft server',
    'fork' => false,
    'url' => 'https://github.com/uncovery/uncovery_me',
    'forks_url' => 'https://api.github.com/repos/uncovery/uncovery_me/forks',
    'keys_url' => 'https://api.github.com/repos/uncovery/uncovery_me/keys{/key_id}',
    'collaborators_url' => 'https://api.github.com/repos/uncovery/uncovery_me/collaborators{/collaborator}',
    'teams_url' => 'https://api.github.com/repos/uncovery/uncovery_me/teams',
    'hooks_url' => 'https://api.github.com/repos/uncovery/uncovery_me/hooks',
    'issue_events_url' => 'https://api.github.com/repos/uncovery/uncovery_me/issues/events{/number}',
    'events_url' => 'https://api.github.com/repos/uncovery/uncovery_me/events',
    'assignees_url' => 'https://api.github.com/repos/uncovery/uncovery_me/assignees{/user}',
    'branches_url' => 'https://api.github.com/repos/uncovery/uncovery_me/branches{/branch}',
    'tags_url' => 'https://api.github.com/repos/uncovery/uncovery_me/tags',
    'blobs_url' => 'https://api.github.com/repos/uncovery/uncovery_me/git/blobs{/sha}',
    'git_tags_url' => 'https://api.github.com/repos/uncovery/uncovery_me/git/tags{/sha}',
    'git_refs_url' => 'https://api.github.com/repos/uncovery/uncovery_me/git/refs{/sha}',
    'trees_url' => 'https://api.github.com/repos/uncovery/uncovery_me/git/trees{/sha}',
    'statuses_url' => 'https://api.github.com/repos/uncovery/uncovery_me/statuses/{sha}',
    'languages_url' => 'https://api.github.com/repos/uncovery/uncovery_me/languages',
    'stargazers_url' => 'https://api.github.com/repos/uncovery/uncovery_me/stargazers',
    'contributors_url' => 'https://api.github.com/repos/uncovery/uncovery_me/contributors',
    'subscribers_url' => 'https://api.github.com/repos/uncovery/uncovery_me/subscribers',
    'subscription_url' => 'https://api.github.com/repos/uncovery/uncovery_me/subscription',
    'commits_url' => 'https://api.github.com/repos/uncovery/uncovery_me/commits{/sha}',
    'git_commits_url' => 'https://api.github.com/repos/uncovery/uncovery_me/git/commits{/sha}',
    'comments_url' => 'https://api.github.com/repos/uncovery/uncovery_me/comments{/number}',
    'issue_comment_url' => 'https://api.github.com/repos/uncovery/uncovery_me/issues/comments{/number}',
    'contents_url' => 'https://api.github.com/repos/uncovery/uncovery_me/contents/{+path}',
    'compare_url' => 'https://api.github.com/repos/uncovery/uncovery_me/compare/{base}...{head}',
    'merges_url' => 'https://api.github.com/repos/uncovery/uncovery_me/merges',
    'archive_url' => 'https://api.github.com/repos/uncovery/uncovery_me/{archive_format}{/ref}',
    'downloads_url' => 'https://api.github.com/repos/uncovery/uncovery_me/downloads',
    'issues_url' => 'https://api.github.com/repos/uncovery/uncovery_me/issues{/number}',
    'pulls_url' => 'https://api.github.com/repos/uncovery/uncovery_me/pulls{/number}',
    'milestones_url' => 'https://api.github.com/repos/uncovery/uncovery_me/milestones{/number}',
    'notifications_url' => 'https://api.github.com/repos/uncovery/uncovery_me/notifications{?since,all,participating}',
    'labels_url' => 'https://api.github.com/repos/uncovery/uncovery_me/labels{/name}',
    'releases_url' => 'https://api.github.com/repos/uncovery/uncovery_me/releases{/id}',
    'created_at' => 1432621128,
    'updated_at' => '2015-06-01T03:38:14Z',
    'pushed_at' => 1434082159,
    'git_url' => 'git://github.com/uncovery/uncovery_me.git',
    'ssh_url' => 'git@github.com:uncovery/uncovery_me.git',
    'clone_url' => 'https://github.com/uncovery/uncovery_me.git',
    'svn_url' => 'https://github.com/uncovery/uncovery_me',
    'homepage' => NULL,
    'size' => 3505,
    'stargazers_count' => 1,
    'watchers_count' => 1,
    'language' => 'PHP',
    'has_issues' => true,
    'has_downloads' => true,
    'has_wiki' => true,
    'has_pages' => false,
    'forks_count' => 2,
    'mirror_url' => NULL,
    'open_issues_count' => 21,
    'forks' => 2,
    'open_issues' => 21,
    'watchers' => 1,
    'default_branch' => 'master',
    'stargazers' => 1,
    'master_branch' => 'master',
  ),
  'pusher' =>
  array (
    'name' => 'uncovery',
    'email' => 'minecraft@uncovery.me',
  ),
  'sender' =>
  array (
    'login' => 'uncovery',
    'id' => 583808,
    'avatar_url' => 'https://avatars.githubusercontent.com/u/583808?v=3',
    'gravatar_id' => '',
    'url' => 'https://api.github.com/users/uncovery',
    'html_url' => 'https://github.com/uncovery',
    'followers_url' => 'https://api.github.com/users/uncovery/followers',
    'following_url' => 'https://api.github.com/users/uncovery/following{/other_user}',
    'gists_url' => 'https://api.github.com/users/uncovery/gists{/gist_id}',
    'starred_url' => 'https://api.github.com/users/uncovery/starred{/owner}{/repo}',
    'subscriptions_url' => 'https://api.github.com/users/uncovery/subscriptions',
    'organizations_url' => 'https://api.github.com/users/uncovery/orgs',
    'repos_url' => 'https://api.github.com/users/uncovery/repos',
    'events_url' => 'https://api.github.com/users/uncovery/events{/privacy}',
    'received_events_url' => 'https://api.github.com/users/uncovery/received_events',
    'type' => 'User',
    'site_admin' => false,
  ),
)
 */