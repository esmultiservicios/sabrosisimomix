<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');

$positions=[
    'bottom-right'=>'Abajo · derecha',
    'bottom-left'=>'Abajo · izquierda',
    'top-right'=>'Arriba · derecha',
    'top-left'=>'Arriba · izquierda',
];
function widget_position_opposite(string $position): string {
    return str_ends_with($position,'left')?str_replace('left','right',$position):str_replace('right','left',$position);
}
function widget_same_side(string $a,string $b): bool {
    return (str_ends_with($a,'left')&&str_ends_with($b,'left'))||(str_ends_with($a,'right')&&str_ends_with($b,'right'));
}
function current_external_widgets(): array {
    $decoded=json_decode((string)setting('floating_widgets_json',''),true);
    if(is_array($decoded)) return array_values(array_filter($decoded,'is_array'));
    $legacyCode=trim((string)setting('floating_widget_code',''));
    if($legacyCode==='') return [];
    return [[
        'id'=>'legacy-chat','enabled'=>setting('floating_widget_enabled','0')==='1','name'=>(string)setting('floating_widget_label','Chat'),
        'install_type'=>'code','url'=>'','code'=>$legacyCode,'position'=>(string)setting('floating_widget_position','bottom-left'),
        'order'=>(int)setting('floating_widget_order','2'),'desktop'=>true,'mobile'=>true,
    ]];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $waEnabled=isset($_POST['floating_whatsapp_enabled'])?'1':'0';
        $waPosition=(string)($_POST['floating_whatsapp_position']??'bottom-right');
        $waOrder=max(1,min(99,(int)($_POST['floating_whatsapp_order']??10)));
        if(!isset($positions[$waPosition])) $waPosition='bottom-right';
        save_setting('floating_whatsapp_enabled',$waEnabled);
        save_setting('floating_whatsapp_position',$waPosition);
        save_setting('floating_whatsapp_order',(string)$waOrder);

        $items=[];$posted=$_POST['widgets']??[];
        if(is_array($posted)){
            foreach($posted as $raw){
                if(!is_array($raw)) continue;
                $name=trim((string)($raw['name']??'Widget'));
                if($name==='') $name='Widget';
                $type=in_array(($raw['install_type']??'code'),['code','url'],true)?(string)$raw['install_type']:'code';
                $position=(string)($raw['position']??widget_position_opposite($waPosition));
                if(!isset($positions[$position])) $position=widget_position_opposite($waPosition);
                if(widget_same_side($position,$waPosition)) $position=widget_position_opposite($position);
                $url=trim((string)($raw['url']??''));
                $code=trim((string)($raw['code']??''));
                if($type==='url'&&$url!==''&&!filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('Uno de los widgets tiene una URL no válida.');
                $items[]=[
                    'id'=>preg_replace('/[^a-zA-Z0-9_-]/','',(string)($raw['id']??''))?:bin2hex(random_bytes(6)),
                    'enabled'=>isset($raw['enabled']),
                    'name'=>substr($name,0,100),
                    'install_type'=>$type,
                    'url'=>substr($url,0,800),
                    'code'=>$code,
                    'position'=>$position,
                    'order'=>max(1,min(99,(int)($raw['order']??20))),
                    'desktop'=>isset($raw['desktop']),
                    'mobile'=>isset($raw['mobile']),
                ];
            }
        }
        save_setting('floating_widgets_json',json_encode($items,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        log_activity('floating_widgets_update','Updated floating widgets',['count'=>count($items)]);
        flash('success','Widgets flotantes guardados correctamente.');
        header('Location: widgets.php');exit;
    }catch(Throwable $e){flash('error',$e->getMessage());header('Location: widgets.php');exit;}
}
$waPosition=(string)setting('floating_whatsapp_position','bottom-left');if(!isset($positions[$waPosition]))$waPosition='bottom-left';
$widgets=current_external_widgets();
if(isset($_GET['add'])){
    $widgets[]=['id'=>'','enabled'=>false,'name'=>'Chat web','install_type'=>'code','url'=>'','code'=>'','position'=>widget_position_opposite($waPosition),'order'=>20,'desktop'=>true,'mobile'=>true];
}
$pageTitle='Floating Widgets';$active='widgets';require __DIR__.'/_header.php';
?>
<div class="page-heading"><div><p class="eyebrow">WIDGETS</p><h1>Widgets flotantes</h1><p class="muted">Administra WhatsApp y agrega chats o integraciones externas. El sistema evita que un widget externo quede en el mismo lado que WhatsApp.</p></div><div class="actions"><a class="button secondary" href="../" target="_blank" rel="noopener">↗ Ver sitio</a></div></div>
<form method="post" class="widget-settings-grid" data-widget-manager>
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<section class="panel widget-config-card full">
  <div class="widget-card-head"><span class="widget-preview-icon whatsapp-preview" aria-hidden="true"><svg viewBox="0 0 32 32"><path fill="currentColor" d="M16 3.2A12.5 12.5 0 0 0 5.1 21.8L3.4 28.5l6.9-1.8A12.5 12.5 0 1 0 16 3.2Zm0 22.7c-2 0-4-.6-5.6-1.6l-.4-.2-4 .9 1-3.8-.3-.4A10.2 10.2 0 1 1 16 25.9Zm5.6-7.7c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-1 1.2-.2.2-.4.2-.7.1-1.9-.9-3.2-1.7-4.5-3.8-.3-.6.3-.6.9-1.8.1-.2 0-.5-.1-.7-.1-.2-.7-1.7-1-2.4-.3-.7-.6-.6-.8-.6h-.7c-.2 0-.6.1-.9.5-.3.3-1.2 1.2-1.2 2.9 0 1.7 1.3 3.4 1.5 3.6.2.2 2.5 3.9 6.1 5.4 2.3 1 3.2 1.1 4.3.9.7-.1 1.8-.7 2.1-1.5.3-.7.3-1.4.2-1.5-.1-.2-.4-.3-.7-.4Z"/></svg></span><div><p class="eyebrow">WHATSAPP</p><h2>Acceso directo principal</h2><p class="muted">El número se toma de Configuración general.</p></div><span class="status-pill is-on">Integrado</span></div>
  <label class="toggle-row"><input type="checkbox" name="floating_whatsapp_enabled" <?=setting('floating_whatsapp_enabled','1')==='1'?'checked':''?>><span>Mostrar WhatsApp</span></label>
  <div class="form-grid"><label>Posición<select name="floating_whatsapp_position" data-wa-position><?php foreach($positions as $key=>$label):?><option value="<?=h($key)?>" <?=$waPosition===$key?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label><label>Orden<input type="number" min="1" max="99" name="floating_whatsapp_order" value="<?=h(setting('floating_whatsapp_order','10'))?>"><small>Un número menor queda más cerca de la esquina.</small></label></div>
</section>

<section class="panel full">
  <div class="section-heading"><div><p class="eyebrow">OTROS WIDGETS</p><h2>Chats e integraciones externas</h2><p>Agrega los que necesites. Los nuevos widgets se colocan automáticamente al lado contrario de WhatsApp.</p></div><a class="button" href="widgets.php?add=1#new-widget">＋ Agregar widget</a></div>
  <div class="widget-info-box"><strong>¿Cómo se instala?</strong><span>Usa “Código de instalación” cuando el proveedor te entregue un snippet <code>&lt;script&gt;...&lt;/script&gt;</code>. Usa “URL embebible” solo cuando el proveedor entregue una dirección diseñada para iframe.</span></div>
  <div data-widget-list>
  <?php foreach($widgets as $i=>$w):?>
    <article class="external-widget-card" data-widget-card <?=$i===count($widgets)-1&&isset($_GET['add'])?'id="new-widget"':''?>>
      <input type="hidden" name="widgets[<?=$i?>][id]" value="<?=h((string)($w['id']??''))?>">
      <div class="external-widget-head"><div><h3><?=h((string)($w['name']??'Widget'))?></h3><small>Posición y orden independientes</small></div><button type="button" class="button danger secondary" data-remove-widget>🗑 Eliminar</button></div>
      <div class="widget-toggle-grid"><label class="toggle-row"><input type="checkbox" name="widgets[<?=$i?>][enabled]" <?=!empty($w['enabled'])?'checked':''?>><span>Activo<small>Mostrar cuando la configuración sea válida.</small></span></label><label class="toggle-row"><input type="checkbox" name="widgets[<?=$i?>][desktop]" <?=!array_key_exists('desktop',$w)||!empty($w['desktop'])?'checked':''?>><span>Computadora / tablet</span></label><label class="toggle-row"><input type="checkbox" name="widgets[<?=$i?>][mobile]" <?=!array_key_exists('mobile',$w)||!empty($w['mobile'])?'checked':''?>><span>Móvil</span></label></div>
      <div class="form-grid">
        <label>Nombre<input name="widgets[<?=$i?>][name]" value="<?=h((string)($w['name']??'Widget'))?>"></label>
        <label>Cómo se instala<select name="widgets[<?=$i?>][install_type]" data-widget-type><option value="code" <?=($w['install_type']??'code')==='code'?'selected':''?>>Código de instalación</option><option value="url" <?=($w['install_type']??'')==='url'?'selected':''?>>URL embebible</option></select></label>
        <label>Posición<select name="widgets[<?=$i?>][position]" data-widget-position><?php foreach($positions as $key=>$label):?><option value="<?=h($key)?>" <?=($w['position']??'')===$key?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label>
        <label>Orden<input type="number" min="1" max="99" name="widgets[<?=$i?>][order]" value="<?=h((string)($w['order']??20))?>"></label>
        <label class="full" data-widget-url>URL embebible<input type="url" name="widgets[<?=$i?>][url]" value="<?=h((string)($w['url']??''))?>" placeholder="https://..."><small>Solo para URLs directas preparadas para ser embebidas.</small></label>
        <label class="full" data-widget-code>Código de instalación<textarea class="code-area" data-rte-off name="widgets[<?=$i?>][code]" spellcheck="false" placeholder="Pega aquí el código completo del proveedor"><?=h((string)($w['code']??''))?></textarea><small>Solo pega código de proveedores confiables.</small></label>
      </div>
    </article>
  <?php endforeach;?>
  </div>
  <div class="form-actions"><button type="submit">💾 Guardar widgets</button></div>
</section>
</form>

<script>
(()=>{
 const root=document.querySelector('[data-widget-manager]');if(!root)return;const wa=root.querySelector('[data-wa-position]');
 const opposite=p=>p.endsWith('left')?p.replace('left','right'):p.replace('right','left');
 const same=(a,b)=>(a.endsWith('left')&&b.endsWith('left'))||(a.endsWith('right')&&b.endsWith('right'));
 const syncCard=card=>{const type=card.querySelector('[data-widget-type]')?.value||'code';card.querySelector('[data-widget-code]')?.toggleAttribute('hidden',type!=='code');card.querySelector('[data-widget-url]')?.toggleAttribute('hidden',type!=='url');};
 const enforce=card=>{const sel=card.querySelector('[data-widget-position]');if(sel&&wa&&same(sel.value,wa.value)){sel.value=opposite(sel.value);sel.dispatchEvent(new Event('change',{bubbles:true}));window.showNotify?.('El widget se movió al lado contrario de WhatsApp para evitar cruces.','info');}};
 const bind=card=>{card.querySelector('[data-remove-widget]')?.addEventListener('click',()=>card.remove());card.querySelector('[data-widget-type]')?.addEventListener('change',()=>syncCard(card));card.querySelector('[data-widget-position]')?.addEventListener('change',()=>enforce(card));syncCard(card);};
 root.querySelectorAll('[data-widget-card]').forEach(bind);wa?.addEventListener('change',()=>root.querySelectorAll('[data-widget-card]').forEach(enforce));
})();
</script>
<?php require __DIR__.'/_footer.php';?>
