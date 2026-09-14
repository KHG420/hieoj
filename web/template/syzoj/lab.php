<?php include("template/$OJ_TEMPLATE/header.php"); ?>
<link rel="stylesheet" href="template/syzoj/css/editorial.css?v=3">
<link rel="stylesheet" href="template/syzoj/css/lab.css?v=3">
<?php
function lab_value($name,$fallback='') { global $formData; return editorial_escape(is_string($_POST[$name] ?? null)?$_POST[$name]:($formData[$name] ?? $fallback)); }
function lab_token() { require './include/set_post_key.php'; }
?>
<main class="ed-page lab-page">
  <div class="ed-heading"><div><h1>ACM 实验室</h1><p>学习算法、参与训练，也一起完善我们的在线评测平台。</p></div></div>
  <nav class="ed-tabs" aria-label="实验室导航">
    <?php foreach(array('about'=>'了解实验室','join'=>'加入我们','feedback'=>'问题与建议','mine'=>'我的申请与反馈') as $key=>$label){ ?><a href="lab.php?tab=<?php echo $key; ?>" <?php if($tab===$key) echo 'aria-current="page"'; ?>><?php echo $label; ?></a><?php } ?>
    <?php if($admin){ ?><a href="lab.php?tab=manage" <?php if($tab==='manage') echo 'aria-current="page"'; ?>>申请与反馈管理</a><a href="lab.php?tab=settings" <?php if($tab==='settings') echo 'aria-current="page"'; ?>>介绍设置</a><?php } ?>
  </nav>
  <?php if($error){ ?><p class="ed-notice ed-error" role="alert"><?php echo editorial_escape($error); ?><?php if(!$user){ ?> <a href="loginpage.php">前往登录</a><?php } ?></p><?php } elseif(isset($_GET['saved'])){ ?><p class="ed-notice" role="status">已保存。你可以在这里查看最新状态和回复。</p><?php } ?>
  <?php if($settings && $tab==='about'){ ?>
    <section class="ed-panel lab-about"><h2>在这里开始你的算法学习</h2><p class="lab-status"><?php echo $settings['recruitment_open']?'招新进行中 · 欢迎零基础同学申请':'暂未开放申请 · 请留意后续通知'; ?></p><div class="lab-prose"><?php echo editorial_escape($settings['introduction']); ?></div>
    <?php if($settings['contact']){ ?><h3>联系方式</h3><p class="lab-prose"><?php echo editorial_escape($settings['contact']); ?></p><?php } ?>
    <div class="ed-actions"><a class="ui primary button" href="lab.php?tab=join">加入我们</a><a class="ui basic button" href="lab.php?tab=feedback&kind=bug">发现 Bug</a><a class="ui basic button" href="lab.php?tab=feedback&kind=suggestion">意见建议</a></div></section>
  <?php } elseif($user && $settings && $tab==='join'){ ?>
    <section class="ed-panel"><h2>加入我们</h2><p class="ed-meta">无需竞赛成绩。介绍你的基础、学习方向和可参与时间，管理员会在这里回复你。资料仅本人和管理员可见。</p>
    <?php if(!$settings['recruitment_open']){ ?><p class="ed-notice">目前暂未开放申请。已有申请的处理进度仍可在“我的申请与反馈”查看。</p>
    <?php } elseif(($active['status'] ?? '')==='accepted'){ ?><p class="ed-notice">你的申请已通过。请在“我的申请与反馈”查看管理员的后续安排。</p>
    <?php } else { ?>
    <?php if(!empty($active)){ ?><p class="ed-notice">你已有一份处理中的申请。保存会更新原申请，不会重复提交。</p><?php } ?>
    <form method="post" class="ed-form lab-form"><?php lab_token(); ?><input type="hidden" name="action" value="submit">
    <?php foreach($fields['join'] as $key=>$label){ ?><label for="lab-<?php echo $key; ?>"><?php echo $label; ?> <span class="ed-meta">必填</span></label>
    <?php if(in_array($key,array('experience','reason','availability'),true)){ ?><textarea id="lab-<?php echo $key; ?>" name="<?php echo $key; ?>" maxlength="3000" rows="4" required <?php if($key==='experience') echo 'placeholder="可以填写零基础，以及你打算从哪里开始学习。"'; ?>><?php echo lab_value($key); ?></textarea>
    <?php } else { ?><input id="lab-<?php echo $key; ?>" name="<?php echo $key; ?>" maxlength="3000" required value="<?php echo lab_value($key); ?>"><?php } ?><?php } ?>
    <p class="ed-meta">每项最多 3000 字。提交前请确认联系方式准确。</p><button class="ui primary button" type="submit"><?php echo !empty($active)?'保存申请修改':'提交加入申请'; ?></button></form><?php } ?></section>
  <?php } elseif($user && $settings && $tab==='feedback'){ $feedbackKind=($_POST['kind'] ?? $kind)==='suggestion'?'suggestion':'bug'; ?>
    <section class="ed-panel"><h2><?php echo $feedbackKind==='bug'?'发现 Bug':'意见建议'; ?></h2>
    <div class="ed-actions"><a href="lab.php?tab=feedback&kind=bug" <?php if($feedbackKind==='bug') echo 'aria-current="page"'; ?>>问题反馈</a><a href="lab.php?tab=feedback&kind=suggestion" <?php if($feedbackKind==='suggestion') echo 'aria-current="page"'; ?>>功能建议</a></div>
    <p class="ed-meta">反馈仅本人和管理员可见。请勿填写密码、密钥或他人的完整个人信息。提交后在“我的申请与反馈”查看处理进度。</p>
    <form method="post" enctype="multipart/form-data" class="ed-form lab-form"><?php lab_token(); ?><input type="hidden" name="action" value="submit"><input type="hidden" name="kind" value="<?php echo $feedbackKind; ?>"><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
    <label for="lab-title">标题 <span class="ed-meta">必填，最多 120 字</span></label><input id="lab-title" name="title" maxlength="120" required value="<?php echo lab_value('title'); ?>" placeholder="例如：从题解页进入金币榜单后无法返回">
    <?php foreach($fields[$feedbackKind] as $key=>$label){ $optional=in_array($key,array('url','device'),true); ?><label for="lab-<?php echo $key; ?>"><?php echo $label; ?> <span class="ed-meta"><?php echo $optional?'选填':'必填'; ?>，最多 3000 字</span></label>
    <textarea id="lab-<?php echo $key; ?>" name="<?php echo $key; ?>" maxlength="3000" rows="<?php echo $optional?2:4; ?>" <?php if(!$optional) echo 'required'; ?>><?php echo lab_value($key,$key==='url' ? (parse_url($_SERVER['HTTP_REFERER'] ?? '',PHP_URL_HOST)===($_SERVER['HTTP_HOST'] ?? '') ? $_SERVER['HTTP_REFERER'] : '') : ''); ?></textarea><?php } ?>
    <label for="lab-screenshot">截图 <span class="ed-meta">选填，PNG / JPEG / WebP，最多 2 MB</span></label><input type="file" id="lab-screenshot" name="screenshot" accept="image/png,image/jpeg,image/webp"><p class="ed-meta">若提交失败，请重新选择截图。</p><button class="ui primary button" type="submit">提交<?php echo $feedbackKind==='bug'?'问题反馈':'功能建议'; ?></button></form></section>
    <script>var deviceField=document.getElementById('lab-device');if(deviceField&&!deviceField.value){deviceField.value=navigator.userAgent+'；屏幕 '+screen.width+' × '+screen.height;}</script>
  <?php } elseif($request && $user && (!$error || http_response_code()===400)){ $data=json_decode($request['details'],true) ?: array(); ?>
    <article class="ed-panel"><a href="lab.php?tab=<?php echo $admin?'manage':'mine'; ?>">返回记录列表</a><h2><?php echo editorial_escape($request['title']); ?></h2><p class="lab-status"><?php echo $kinds[$request['kind']].' · '.$states[$request['kind']][$request['status']]; ?></p><p class="ed-meta">提交于 <?php echo editorial_escape($request['created_at']); ?> · 更新于 <?php echo editorial_escape($request['updated_at']); ?><?php if($admin) echo ' · 用户 '.editorial_escape($request['user_id']); ?></p>
    <dl class="lab-details"><?php foreach($fields[$request['kind']] as $key=>$label){ ?><dt><?php echo $label; ?></dt><dd><?php echo editorial_escape($data[$key] ?? '') ?: '未填写'; ?></dd><?php } ?></dl>
    <?php if($request['screenshot_mime']){ ?><p><a href="lab.php?tab=mine&id=<?php echo $id; ?>&amp;attachment=1" target="_blank" rel="noopener noreferrer">查看截图（新窗口打开）</a></p><?php } ?>
    <h3>管理员回复</h3><p class="lab-prose"><?php echo $request['reply']?editorial_escape($request['reply']):'暂无回复，请稍后在此查看处理进度。'; ?></p>
    <?php if(!$admin && $request['kind']==='join' && in_array($request['status'],array('pending','talking'),true)){ ?><a class="ui basic button" href="lab.php?tab=join">修改申请</a><?php } ?>
    <?php if($admin){ ?><form method="post" class="ed-form lab-form lab-review"><?php lab_token(); ?><input type="hidden" name="action" value="review"><h3>处理这条记录</h3><label for="lab-status">处理状态</label><select name="status" id="lab-status"><?php foreach($states[$request['kind']] as $value=>$label){ ?><option value="<?php echo $value; ?>" <?php if($request['status']===$value) echo 'selected'; ?>><?php echo $label; ?></option><?php } ?></select>
    <label for="lab-reply">回复用户（变更状态时必填，最多 5000 字）</label><textarea name="reply" id="lab-reply" rows="5" maxlength="5000"><?php echo lab_value('reply',$request['reply']); ?></textarea>
    <label for="lab-note">管理备注（仅管理员可见，最多 5000 字）</label><textarea name="admin_note" id="lab-note" rows="3" maxlength="5000"><?php echo lab_value('admin_note',$request['admin_note']); ?></textarea><button class="ui primary button" type="submit">保存处理结果</button></form><?php } ?></article>
  <?php } elseif($user && !$error && in_array($tab,array('mine','manage'),true)){ ?>
    <section class="ed-panel"><h2><?php echo $tab==='manage'?'申请与反馈管理':'我的申请与反馈'; ?></h2><form method="get" class="lab-filters"><input type="hidden" name="tab" value="<?php echo $tab; ?>"><div class="lab-filter-field"><label for="lab-kind">类型</label><select id="lab-kind" name="kind"><option value="">全部类型</option><?php foreach($kinds as $value=>$label){ ?><option value="<?php echo $value; ?>" <?php if($kind===$value) echo 'selected'; ?>><?php echo $label; ?></option><?php } ?></select></div><div class="lab-filter-field"><label for="lab-filter-status">状态</label><select id="lab-filter-status" name="status"><option value="">全部状态</option><?php foreach(array('pending'=>'待处理 / 待确认','talking'=>'沟通中','accepted'=>'申请已通过','rejected'=>'申请未通过','confirmed'=>'已确认','working'=>'处理中','resolved'=>'已解决 / 已采纳','declined'=>'暂不处理 / 暂不采纳') as $value=>$label){ ?><option value="<?php echo $value; ?>" <?php if(($filterStatus ?? '')===$value) echo 'selected'; ?>><?php echo $label; ?></option><?php } ?></select></div><button class="ui basic button" type="submit">筛选</button></form>
    <?php if(!$rows){ ?><p class="ed-empty">暂无符合条件的记录。你可以申请加入实验室，或提交问题与建议。</p><?php } ?>
    <?php foreach($rows as $row){ ?><div class="ed-row"><div><a class="ed-row-title" href="lab.php?tab=<?php echo $tab; ?>&amp;id=<?php echo $row['id']; ?>"><?php echo editorial_escape($row['title']); ?></a><p class="ed-meta"><?php echo $kinds[$row['kind']].' · '.editorial_escape($row['created_at']); ?><?php if($admin) echo ' · '.editorial_escape($row['user_id']); ?></p></div><span class="ed-status"><?php echo $states[$row['kind']][$row['status']]; ?></span></div><?php } ?></section>
    <?php if($page>1 || $hasNext){ ?><nav class="ed-pagination" aria-label="记录分页"><?php if($page>1){ ?><a href="lab.php?<?php echo editorial_escape(http_build_query(array('tab'=>$tab,'kind'=>$kind,'status'=>$filterStatus ?? '', 'page'=>$page-1))); ?>">上一页</a><?php } ?><span>第 <?php echo $page; ?> 页</span><?php if($hasNext){ ?><a href="lab.php?<?php echo editorial_escape(http_build_query(array('tab'=>$tab,'kind'=>$kind,'status'=>$filterStatus ?? '', 'page'=>$page+1))); ?>">下一页</a><?php } ?></nav><?php } ?>
  <?php } elseif($admin && $settings && $tab==='settings'){ ?>
    <section class="ed-panel"><h2>实验室介绍设置</h2><form method="post" class="ed-form lab-form"><?php lab_token(); ?><input type="hidden" name="action" value="settings"><label for="lab-intro">实验室介绍（最多 10000 字）</label><textarea id="lab-intro" name="introduction" rows="12" maxlength="10000" required><?php echo lab_value('introduction',$settings['introduction']); ?></textarea><p class="ed-meta">可介绍训练方向、活动安排和加入要求。按纯文本展示，保留换行。</p><label for="lab-contact">联系方式（最多 500 字）</label><textarea id="lab-contact" name="contact" rows="3" maxlength="500"><?php echo lab_value('contact',$settings['contact']); ?></textarea><label class="lab-check"><input type="checkbox" name="recruitment_open" value="1" <?php if($_SERVER['REQUEST_METHOD']==='POST'?isset($_POST['recruitment_open']):$settings['recruitment_open']) echo 'checked'; ?>> 开放加入申请</label><button class="ui primary button" type="submit">保存介绍设置</button></form></section>
  <?php } ?>
</main>
<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
