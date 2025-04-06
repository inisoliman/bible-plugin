<?php
function bible_rewrite_rules() {
    // قاعدة لصفحة القراءة
    add_rewrite_rule(
        '^bible_read/?$',
        'index.php?pagename=bible_read',
        'top'
    );

    // قاعدة لعرض أصحاح أو آية
    add_rewrite_rule(
        '^bible/([^/]+)/([0-9]+)/([0-9]+)/?$',
        'index.php?pagename=bible&book=$matches[1]&chapter=$matches[2]&verse=$matches[3]',
        'top'
    );

    // قاعدة لعرض أصحاح كامل
    add_rewrite_rule(
        '^bible/([^/]+)/([0-9]+)/?$',
        'index.php?pagename=bible&book=$matches[1]&chapter=$matches[2]',
        'top'
    );

    // قاعدة لصفحة bible مباشرة
    add_rewrite_rule(
        '^bible/?$',
        'index.php?pagename=bible',
        'top'
    );
}
add_action('init', 'bible_rewrite_rules');

function bible_query_vars($vars) {
    $vars[] = 'book';
    $vars[] = 'chapter';
    $vars[] = 'verse';
    return $vars;
}
add_filter('query_vars', 'bible_query_vars');