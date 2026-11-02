<?php
function oj_nav_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function oj_adventure_tabs() {
    return array('enemy'=>'昨日之敌', 'route'=>'算法远征', 'shadow'=>'影子挑战',
        'hunt'=>'反例猎人', 'campus'=>'校园共同挑战', 'memoir'=>'解题回忆录');
}

// Carry only a known local destination, never a caller-provided return URL.
function oj_adventure_origin($data) {
    $origin = $data['from_adventure'] ?? '';
    return is_string($origin) && isset(oj_adventure_tabs()[$origin]) ? $origin : '';
}

function oj_adventure_query($origin, $html = true) {
    return oj_adventure_origin(array('from_adventure'=>$origin)) !== ''
        ? ($html ? '&amp;' : '&').'from_adventure='.$origin : '';
}
