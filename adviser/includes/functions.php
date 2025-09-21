<?php
function timeAgo($datetime) {
    date_default_timezone_set('Asia/Manila');
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d) {
        if ($diff->d == 1) return 'Yesterday';
        if ($diff->d < 7) return $diff->d . ' days ago';
        return date('M d, Y h:iA', strtotime($datetime));
    }
    if ($diff->h) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    if ($diff->s) return $diff->s . ' second' . ($diff->s > 1 ? 's' : '') . ' ago';
    return 'just now';
}

function formatPosterLabel($role, $org, $name) {
    if (!$name) $name = 'Unknown User';
    if ($role === 'aca_coordinator') return "$name (ACA_Coordinator)";
    if ($role === 'adviser') return "$name (" . ($org ? "$org Adviser" : "Adviser") . ")";
    if ($role === 'member') return "$name (" . ($org ? "$org Member" : "Member") . ")";
    return "$name (" . ucfirst($role) . ")";
}

function formatNotifActor($role, $org, $name) {
    if (!$name) $name = 'Unknown User';
    if ($role === 'aca_coordinator') return "$name (ACA_Coordinator)";
    if ($role === 'adviser') return "$name (" . ($org ? "$org Adviser" : "Adviser") . ")";
    if ($role === 'member') return "$name (" . ($org ? "$org Member" : "Member") . ")";
    return "$name (" . ucfirst($role) . ")";
}


?>

