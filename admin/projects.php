<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('projects.manage');
$pdo=db();

function save_project_image(?array $f): ?string {
    if(!$f || ($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    if((int)$f['error']!==UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir la imagen.');
    if((int)($f['size']??0)>8*1024*1024) throw new RuntimeException('La imagen no puede superar 8 MB.');
    $mime=secure_file_mime_type($f['tmp_name']);
    $map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($map[$mime])) throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WEBP.');
    $dir=UPLOAD_DIR.'/projects';
    if(!is_dir($dir)) mkdir($dir,0755,true);
    $name=bin2hex(random_bytes(12)).'.'.$map[$mime];
    if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('No se pudo guardar la imagen.');
    return 'uploads/projects/'.$name;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $action=(string)($_POST['action']??'save');
        $id=(int)($_POST['id']??0);
        if($action==='delete'){
            $st=$pdo->prepare('SELECT image_path FROM projects WHERE id=?');$st->execute([$id]);
            $old=(string)($st->fetchColumn()?:'');
            $pdo->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
            if($old!=='' && str_starts_with($old,'uploads/projects/')) @unlink(ROOT_DIR.'/'.$old);
            flash('success','Proyecto eliminado.');
        }else{
            $title=trim((string)($_POST['title']??''));
            if($title==='') throw new RuntimeException('Escribe el nombre del proyecto.');
            $image=save_project_image($_FILES['image']??null);
            if($id){
                $old=$pdo->prepare('SELECT image_path FROM projects WHERE id=?');$old->execute([$id]);
                $current=(string)$old->fetchColumn();
                $image=$image?:$current;
                $pdo->prepare('UPDATE projects SET title=?,category=?,description=?,image_path=?,external_url=?,sort_order=?,active=? WHERE id=?')
                    ->execute([$title,trim((string)($_POST['category']??'')),trim((string)($_POST['description']??'')),$image,trim((string)($_POST['external_url']??'')),(int)($_POST['sort_order']??0),isset($_POST['active'])?1:0,$id]);
            }else{
                $pdo->prepare('INSERT INTO projects(title,category,description,image_path,external_url,sort_order,active) VALUES(?,?,?,?,?,?,?)')
                    ->execute([$title,trim((string)($_POST['category']??'')),trim((string)($_POST['description']??'')),$image,trim((string)($_POST['external_url']??'')),(int)($_POST['sort_order']??0),isset($_POST['active'])?1:0]);
            }
            flash('success','Proyecto guardado.');
        }
        header('Location: projects.php');exit;
    }catch(Throwable $e){
        flash('error',$e->getMessage());header('Location: projects.php');exit;
    }
}

$edit=null;
if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM projects WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();}
$rows=$pdo->query('SELECT * FROM projects ORDER BY sort_order,id DESC')->fetchAll();
$pageTitle='Projects';$active='projects';require __DIR__.'/_header.php';
?>
<div class="page-heading"><div><p class="eyebrow">PORTAFOLIO</p><h1>Projects</h1><p class="muted">Agrega montajes, eventos, servicios destacados o casos.</p></div></div>
<section class="panel"><h2><?=$edit?'Editar proyecto':'Add project'?></h2>
<form class="crud-form" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=h($edit['id']??0)?>">
<label>Nombre<input name="title" value="<?=h($edit['title']??'')?>" required></label>
<label>Categoría<input name="category" value="<?=h($edit['category']??'')?>"></label>
<label class="full">Descripción<textarea name="description"><?=h($edit['description']??'')?></textarea></label>
<div class="full admin-upload-field">
  <div class="field-heading"><div><strong>Imagen del proyecto</strong><small>Arrastra y suelta, pega desde el portapapeles o selecciona una imagen. JPG, PNG o WEBP · máximo 8 MB.</small></div></div>
  <div class="premium-media-zone" data-upload-zone tabindex="0" aria-label="Cargar imagen del proyecto">
    <input class="visually-hidden-file" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-max-files="1" data-max-total-bytes="8388608" data-empty-label="No hay una imagen nueva seleccionada.">
    <div class="upload-icon" aria-hidden="true">⇧</div>
    <strong>Arrastra y suelta tu imagen aquí</strong>
    <small>También puedes pegar con Ctrl + V o usar el selector.</small>
    <button type="button" class="upload-zone-action" data-upload-choose>Seleccionar imagen</button>
    <div class="upload-selection-name" data-upload-name>No hay una imagen nueva seleccionada.</div>
    <div class="premium-upload-preview" data-upload-preview aria-live="polite"></div>
  </div>
  <?php if(!empty($edit['image_path'])):?><div class="current-upload-preview"><span>Imagen actual</span><img src="../<?=h($edit['image_path'])?>" alt="Vista previa del proyecto"></div><?php endif;?>
</div>
<label>URL opcional<input type="url" name="external_url" value="<?=h($edit['external_url']??'')?>"></label>
<label>Orden<input type="number" name="sort_order" value="<?=h($edit['sort_order']??0)?>"></label>
<label class="status-field"><span class="field-label">Estado</span><span class="check-control"><input type="checkbox" name="active" value="1" <?=!$edit||(int)$edit['active']===1?'checked':''?>><span>Activo</span></span></label>
<div class="full form-actions"><button>Guardar</button><?php if($edit):?><a class="button secondary" href="projects.php">Cancelar</a><?php endif;?></div>
</form></section>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Imagen</th><th>Proyecto</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?php if($r['image_path']):?><img class="preview-thumb" src="../<?=h($r['image_path'])?>" alt=""><?php else:?>—<?php endif;?></td><td><strong><?=h($r['title'])?></strong><br><small><?=h($r['category'])?></small></td><td><span class="badge <?=$r['active']?'active':''?>"><?=$r['active']?'Activo':'Oculto'?></span></td><td><div class="actions"><a class="button secondary small" href="?edit=<?=$r['id']?>">Editar</a><form method="post" data-premium-confirm data-confirm-title="Eliminar proyecto" data-confirm-message="¿Eliminar este proyecto? Esta acción no se puede deshacer." data-confirm-ok="Sí, eliminar" data-confirm-cancel="Cancelar"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="danger small">Eliminar</button></form></div></td></tr><?php endforeach;?>
</tbody></table></div></section>
<?php require __DIR__.'/_footer.php';?>
