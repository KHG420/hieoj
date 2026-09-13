<?php
require_once('admin-header.php');
require_once('../include/knowledge_graph.inc.php');
if (!isset($_SESSION[$OJ_NAME.'_'.'administrator'])) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}
if (!kg_schema_ready()) {
    echo '<div class="alert alert-danger">知识图谱数据库表尚未初始化。</div>';
    exit(1);
}

$notice = '';
$error = '';
function kg_admin_cycle($source, $target) {
    $rows = pdo_query("SELECT source_node_id,target_node_id FROM knowledge_edge WHERE relation='prerequisite'");
    $next = array();
    foreach ($rows as $row) $next[intval($row['source_node_id'])][] = intval($row['target_node_id']);
    $stack = array($target); $seen = array();
    while ($stack) {
        $current = array_pop($stack);
        if ($current === $source) return true;
        if (isset($seen[$current])) continue;
        $seen[$current] = true;
        if (isset($next[$current])) foreach ($next[$current] as $id) $stack[] = $id;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once('../include/check_post_key.php');
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'save_node') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $slug = trim(isset($_POST['slug']) ? $_POST['slug'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $domain = trim(isset($_POST['domain']) ? $_POST['domain'] : '');
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
        $sort = intval(isset($_POST['sort_order']) ? $_POST['sort_order'] : 0);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $slug) || $name === '' || $domain === '') {
            $error = '节点标识仅允许小写字母、数字和连字符，名称与领域不能为空。';
        } else if ($id) {
            $ok = pdo_query("UPDATE knowledge_node SET slug=?,name=?,domain=?,description=?,sort_order=? WHERE id=?", $slug,$name,$domain,$description,$sort,$id);
            $notice = $ok === false ? '' : '知识节点已更新。'; if ($ok === false) $error = '节点更新失败，标识可能已经存在。';
        } else {
            $ok = pdo_query("INSERT INTO knowledge_node(slug,name,domain,description,sort_order) VALUES(?,?,?,?,?)", $slug,$name,$domain,$description,$sort);
            $notice = $ok === false ? '' : '知识节点已新增。'; if ($ok === false) $error = '节点新增失败，标识可能已经存在。';
        }
    } else if ($action === 'toggle_node') {
        pdo_query("UPDATE knowledge_node SET status=IF(status=1,0,1) WHERE id=?", intval($_POST['id']));
        $notice = '节点公开状态已切换。';
    } else if ($action === 'add_edge') {
        $source = intval($_POST['source_node_id']); $target = intval($_POST['target_node_id']);
        $relation = isset($_POST['relation']) ? $_POST['relation'] : 'related';
        if (!in_array($relation,array('prerequisite','strong','related'),true) || !$source || !$target || $source === $target) $error = '关系参数无效。';
        else if ($relation === 'prerequisite' && kg_admin_cycle($source,$target)) $error = '不能添加这条前置关系：它会形成循环依赖。';
        else { pdo_query("INSERT IGNORE INTO knowledge_edge(source_node_id,target_node_id,relation) VALUES(?,?,?)",$source,$target,$relation); $notice='知识关系已保存。'; }
    } else if ($action === 'remove_edge') {
        pdo_query("DELETE FROM knowledge_edge WHERE source_node_id=? AND target_node_id=? AND relation=?",intval($_POST['source_node_id']),intval($_POST['target_node_id']),$_POST['relation']);
        $notice = '知识关系已移除。';
    } else if ($action === 'add_mapping') {
        pdo_query("INSERT IGNORE INTO knowledge_node_category(node_id,category_id) VALUES(?,?)",intval($_POST['node_id']),intval($_POST['category_id']));
        $notice = '标签映射已保存，前台题量会自动更新。';
    } else if ($action === 'remove_mapping') {
        pdo_query("DELETE FROM knowledge_node_category WHERE node_id=? AND category_id=?",intval($_POST['node_id']),intval($_POST['category_id']));
        $notice = '标签映射已移除。';
    } else if ($action === 'add_alias') {
        $nodeId = intval($_POST['node_id']);
        $tagText = trim(isset($_POST['tag_text']) ? $_POST['tag_text'] : '');
        if (!$nodeId || $tagText === '' || mb_strlen($tagText, 'UTF-8') > 191) $error = '兼容标签不能为空且不能超过 191 个字符。';
        else { pdo_query("INSERT IGNORE INTO knowledge_node_tag_alias(node_id,tag_text) VALUES(?,?)",$nodeId,$tagText); $notice='兼容标签已保存，匹配题目会自动进入该知识节点。'; }
    } else if ($action === 'remove_alias') {
        pdo_query("DELETE FROM knowledge_node_tag_alias WHERE node_id=? AND tag_text=?",intval($_POST['node_id']),$_POST['tag_text']);
        $notice = '兼容标签已移除。';
    }
}

$nodes = pdo_query("SELECT id,slug,name,domain,description,sort_order,status FROM knowledge_node ORDER BY domain,sort_order,id");
$edges = pdo_query("SELECT e.source_node_id,e.target_node_id,e.relation,s.name source_name,t.name target_name FROM knowledge_edge e JOIN knowledge_node s ON s.id=e.source_node_id JOIN knowledge_node t ON t.id=e.target_node_id ORDER BY e.relation,s.name,t.name");
$mappings = pdo_query("SELECT m.node_id,m.category_id,n.name node_name,c.`content-1`,c.`content-2` FROM knowledge_node_category m JOIN knowledge_node n ON n.id=m.node_id JOIN category c ON c.id=m.category_id ORDER BY n.domain,n.sort_order,c.priority");
$aliases = pdo_query("SELECT a.node_id,a.tag_text,n.name node_name,n.domain FROM knowledge_node_tag_alias a JOIN knowledge_node n ON n.id=a.node_id ORDER BY n.domain,n.sort_order,a.tag_text");
$categories = pdo_query("SELECT id,`content-1`,`content-2` FROM category WHERE status=0 ORDER BY priority,id");
$governance = kg_load_graph(null)['governance'];
$editNode = null;
if (isset($_GET['edit'])) {
    $editRows = pdo_query("SELECT id,slug,name,domain,description,sort_order FROM knowledge_node WHERE id=?", intval($_GET['edit']));
    if ($editRows) $editNode = $editRows[0];
}
?>
<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>知识地图管理</title>
<style>
.kg-admin{padding:18px;max-width:1500px;margin:auto}.kg-admin h1{font-size:25px}.kg-admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.kg-box{background:#fff;border:1px solid #dfe4ea;border-radius:10px;padding:16px;margin-bottom:16px}.kg-box h2{font-size:17px;margin:0 0 12px}.kg-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.kg-form .wide{grid-column:1/-1}.kg-form input,.kg-form select,.kg-form textarea{width:100%;border:1px solid #cfd6df;border-radius:5px;padding:8px}.kg-form button,.kg-inline button{border:0;border-radius:5px;padding:8px 12px;background:#2864d7;color:#fff;cursor:pointer}.kg-inline{display:inline-flex;align-items:center;gap:5px;margin:3px}.kg-inline button.danger{background:#b93e48}.kg-table{width:100%;border-collapse:collapse;font-size:13px}.kg-table th,.kg-table td{border-bottom:1px solid #edf0f3;padding:8px;text-align:left}.kg-badge{display:inline-block;background:#edf3fc;color:#31558d;border-radius:20px;padding:3px 8px;margin:2px}.kg-warning{background:#fff6e7;color:#8a5000;padding:10px;border-radius:7px}.kg-success{background:#eaf8f1;color:#126342;padding:10px;border-radius:7px}.kg-error{background:#fff0f1;color:#9c2934;padding:10px;border-radius:7px}@media(max-width:900px){.kg-admin-grid{grid-template-columns:1fr}.kg-form{grid-template-columns:1fr}}
</style></head><body class="hold-transition sidebar-mini layout-fixed"><div class="wrapper"><?php include('navbar.php'); ?><div class="content-wrapper"><main class="kg-admin">
<h1>知识地图管理</h1>
<?php if($notice) echo '<p class="kg-success">'.htmlentities($notice,ENT_QUOTES,'UTF-8').'</p>'; ?>
<?php if($error) echo '<p class="kg-error">'.htmlentities($error,ENT_QUOTES,'UTF-8').'</p>'; ?>
<div class="kg-admin-grid"><div>
<section class="kg-box"><h2><?php echo $editNode?'编辑':'新增'; ?>知识节点</h2><form method="post" class="kg-form"><?php require('../include/set_post_key.php'); ?><input type="hidden" name="action" value="save_node"><input type="hidden" name="id" value="<?php echo $editNode?intval($editNode['id']):0; ?>"><input name="slug" value="<?php echo $editNode?htmlentities($editNode['slug'],ENT_QUOTES,'UTF-8'):''; ?>" placeholder="唯一标识，如 shortest-path" required pattern="[a-z0-9][a-z0-9-]{1,63}"><input name="name" value="<?php echo $editNode?htmlentities($editNode['name'],ENT_QUOTES,'UTF-8'):''; ?>" placeholder="节点名称" required><input name="domain" value="<?php echo $editNode?htmlentities($editNode['domain'],ENT_QUOTES,'UTF-8'):''; ?>" placeholder="所属领域" required><input name="sort_order" type="number" value="<?php echo $editNode?intval($editNode['sort_order']):10; ?>" placeholder="排序"><textarea class="wide" name="description" rows="2" placeholder="学习目标和知识说明"><?php echo $editNode?htmlentities($editNode['description'],ENT_QUOTES,'UTF-8'):''; ?></textarea><button class="wide" type="submit"><?php echo $editNode?'保存修改':'新增节点'; ?></button></form><?php if($editNode){ ?><p><a href="knowledge_graph.php">取消编辑</a></p><?php } ?></section>
<section class="kg-box"><h2>知识节点</h2><table class="kg-table"><thead><tr><th>领域 / 节点</th><th>标识</th><th>编辑</th><th>状态</th></tr></thead><tbody><?php foreach($nodes as $node){ ?><tr><td><?php echo htmlentities($node['domain'].' / '.$node['name'],ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlentities($node['slug'],ENT_QUOTES,'UTF-8'); ?></td><td><a href="knowledge_graph.php?edit=<?php echo intval($node['id']); ?>">编辑</a></td><td><form method="post" class="kg-inline"><?php require('../include/set_post_key.php'); ?><input type="hidden" name="action" value="toggle_node"><input type="hidden" name="id" value="<?php echo intval($node['id']); ?>"><button type="submit" class="<?php echo $node['status']?'':'danger'; ?>"><?php echo $node['status']?'公开':'停用'; ?></button></form></td></tr><?php } ?></tbody></table></section>
</div><div>
<section class="kg-box"><h2>添加知识关系</h2><form method="post" class="kg-form"><?php require('../include/set_post_key.php'); ?><input type="hidden" name="action" value="add_edge"><select name="source_node_id"><?php foreach($nodes as $n) echo '<option value="'.intval($n['id']).'">'.htmlentities($n['domain'].' / '.$n['name'],ENT_QUOTES,'UTF-8').'</option>'; ?></select><select name="target_node_id"><?php foreach($nodes as $n) echo '<option value="'.intval($n['id']).'">'.htmlentities($n['domain'].' / '.$n['name'],ENT_QUOTES,'UTF-8').'</option>'; ?></select><select name="relation"><option value="prerequisite">前置知识</option><option value="strong">强关联</option><option value="related">相关</option></select><button type="submit">保存关系</button></form></section>
<section class="kg-box"><h2>添加节点—标签映射</h2><form method="post" class="kg-form"><?php require('../include/set_post_key.php'); ?><input type="hidden" name="action" value="add_mapping"><select name="node_id"><?php foreach($nodes as $n) echo '<option value="'.intval($n['id']).'">'.htmlentities($n['domain'].' / '.$n['name'],ENT_QUOTES,'UTF-8').'</option>'; ?></select><select name="category_id"><?php foreach($categories as $c) echo '<option value="'.intval($c['id']).'">'.htmlentities(kg_category_key($c['content-1'],$c['content-2']),ENT_QUOTES,'UTF-8').'</option>'; ?></select><button class="wide" type="submit">保存映射</button></form></section>
<section class="kg-box"><h2>添加兼容题目标签</h2><p>用于把历史写法或未进入分类目录的原始标签映射到知识节点，不会修改题目的原始标签。</p><form method="post" class="kg-form"><?php require('../include/set_post_key.php'); ?><input type="hidden" name="action" value="add_alias"><select name="node_id"><?php foreach($nodes as $n) echo '<option value="'.intval($n['id']).'">'.htmlentities($n['domain'].' / '.$n['name'],ENT_QUOTES,'UTF-8').'</option>'; ?></select><input name="tag_text" maxlength="191" placeholder="题目中的完整标签文本" required><button class="wide" type="submit">保存兼容标签</button></form></section>
<section class="kg-box"><h2>覆盖率与标签治理</h2><p><b><?php echo intval($governance['coverage']); ?>%</b> 的公开题目已进入知识地图（<?php echo intval($governance['mapped_problem_count']).'/'.intval($governance['active_problem_count']); ?>），仍有 <b><?php echo intval($governance['unmapped_problem_count']); ?></b> 道待标注，其中 <?php echo intval($governance['blank_tag_problem_count']); ?> 道没有任何标签。</p><p>检测到 <b><?php echo intval($governance['unmapped_tag_count']); ?></b> 个题目标签尚未映射。</p><div><?php foreach($governance['unmapped_tags'] as $tag) echo '<span class="kg-badge">'.htmlentities($tag['name'],ENT_QUOTES,'UTF-8').' · '.intval($tag['problem_count']).'</span>'; ?></div><p class="kg-warning">知识标签应映射到节点；比赛、院校、训练集等来源标签无需伪装成知识点。系统不会根据标签共现自动创建前置关系。</p></section>
</div></div>
<section class="kg-box"><h2>现有关系</h2><?php foreach($edges as $edge){ ?><form method="post" class="kg-inline"><?php require('../include/set_post_key.php'); ?><input type="hidden" name="action" value="remove_edge"><input type="hidden" name="source_node_id" value="<?php echo intval($edge['source_node_id']); ?>"><input type="hidden" name="target_node_id" value="<?php echo intval($edge['target_node_id']); ?>"><input type="hidden" name="relation" value="<?php echo htmlentities($edge['relation'],ENT_QUOTES,'UTF-8'); ?>"><span class="kg-badge"><?php echo htmlentities($edge['source_name'].' → '.$edge['target_name'].' / '.$edge['relation'],ENT_QUOTES,'UTF-8'); ?></span><button class="danger" type="submit">移除</button></form><?php } ?></section>
<section class="kg-box"><h2>现有标签映射</h2><?php foreach($mappings as $mapping){ ?><form method="post" class="kg-inline"><?php require('../include/set_post_key.php'); ?><input type="hidden" name="action" value="remove_mapping"><input type="hidden" name="node_id" value="<?php echo intval($mapping['node_id']); ?>"><input type="hidden" name="category_id" value="<?php echo intval($mapping['category_id']); ?>"><span class="kg-badge"><?php echo htmlentities($mapping['node_name'].' ← '.kg_category_key($mapping['content-1'],$mapping['content-2']),ENT_QUOTES,'UTF-8'); ?></span><button class="danger" type="submit">移除</button></form><?php } ?></section>
<section class="kg-box"><h2>现有兼容标签</h2><?php foreach($aliases as $alias){ ?><form method="post" class="kg-inline"><?php require('../include/set_post_key.php'); ?><input type="hidden" name="action" value="remove_alias"><input type="hidden" name="node_id" value="<?php echo intval($alias['node_id']); ?>"><input type="hidden" name="tag_text" value="<?php echo htmlentities($alias['tag_text'],ENT_QUOTES,'UTF-8'); ?>"><span class="kg-badge"><?php echo htmlentities($alias['node_name'].' ← '.$alias['tag_text'],ENT_QUOTES,'UTF-8'); ?></span><button class="danger" type="submit">移除</button></form><?php } ?></section>
<section class="kg-box"><h2>待标注题目（前 100 道）</h2><table class="kg-table"><thead><tr><th>题号</th><th>题目</th><th>当前标签</th></tr></thead><tbody><?php foreach($governance['unmapped_problems'] as $problem){ ?><tr><td><a href="../problem.php?id=<?php echo intval($problem['id']); ?>"><?php echo intval($problem['id']); ?></a></td><td><?php echo htmlentities($problem['title'],ENT_QUOTES,'UTF-8'); ?></td><td><?php echo count($problem['tags'])?htmlentities(implode(' / ',$problem['tags']),ENT_QUOTES,'UTF-8'):'<span class="kg-warning">无标签</span>'; ?></td></tr><?php } ?></tbody></table></section>
</main></div></div></body></html>
