<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');

$positions = [
    'bottom-right' => 'Abajo · derecha',
    'bottom-left' => 'Abajo · izquierda',
    'top-right' => 'Arriba · derecha',
    'top-left' => 'Arriba · izquierda',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $waEnabled = isset($_POST['floating_whatsapp_enabled']) ? '1' : '0';
        $waPosition = (string)($_POST['floating_whatsapp_position'] ?? 'bottom-right');
        $waOrder = max(1, min(20, (int)($_POST['floating_whatsapp_order'] ?? 1)));
        $widgetEnabled = isset($_POST['floating_widget_enabled']) ? '1' : '0';
        $widgetPosition = (string)($_POST['floating_widget_position'] ?? 'bottom-left');
        $widgetOrder = max(1, min(20, (int)($_POST['floating_widget_order'] ?? 2)));
        $widgetCode = trim((string)($_POST['floating_widget_code'] ?? ''));
        $widgetLabel = trim((string)($_POST['floating_widget_label'] ?? 'Chat'));

        if (!isset($positions[$waPosition])) $waPosition = 'bottom-right';
        if (!isset($positions[$widgetPosition])) $widgetPosition = 'bottom-left';

        save_setting('floating_whatsapp_enabled', $waEnabled);
        save_setting('floating_whatsapp_position', $waPosition);
        save_setting('floating_whatsapp_order', (string)$waOrder);
        save_setting('floating_widget_enabled', $widgetEnabled);
        save_setting('floating_widget_position', $widgetPosition);
        save_setting('floating_widget_order', (string)$widgetOrder);
        save_setting('floating_widget_code', $widgetCode);
        save_setting('floating_widget_label', $widgetLabel);
        log_activity('floating_widgets_update', 'Updated floating widget settings');
        flash('success', 'Configuración de widgets flotantes guardada.');
        header('Location: widgets.php');
        exit;
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        header('Location: widgets.php');
        exit;
    }
}

$pageTitle='Floating Widgets';
$active='widgets';
require __DIR__.'/_header.php';
?>
<div class="page-heading">
  <div>
    <p class="eyebrow">EXPERIENCIA DEL SITIO</p>
    <h1>Widgets flotantes</h1>
    <p class="muted">Decide qué accesos flotantes mostrar, su posición y el orden cuando compartan una misma esquina.</p>
  </div>
  <div class="actions"><a class="button secondary" href="../" target="_blank" rel="noopener">↗ Ver sitio</a></div>
</div>

<form method="post" class="widget-settings-grid">
  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

  <section class="panel widget-config-card">
    <div class="widget-card-head">
      <span class="widget-preview-icon whatsapp-preview" aria-hidden="true">
        <svg viewBox="0 0 32 32"><path fill="currentColor" d="M16 3.2A12.5 12.5 0 0 0 5.1 21.8L3.4 28.5l6.9-1.8A12.5 12.5 0 1 0 16 3.2Zm0 22.7c-2 0-4-.6-5.6-1.6l-.4-.2-4 .9 1-3.8-.3-.4A10.2 10.2 0 1 1 16 25.9Zm5.6-7.7c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-1 1.2-.2.2-.4.2-.7.1-1.9-.9-3.2-1.7-4.5-3.8-.3-.6.3-.6.9-1.8.1-.2 0-.5-.1-.7-.1-.2-.7-1.7-1-2.4-.3-.7-.6-.6-.8-.6h-.7c-.2 0-.6.1-.9.5-.3.3-1.2 1.2-1.2 2.9 0 1.7 1.3 3.4 1.5 3.6.2.2 2.5 3.9 6.1 5.4 2.3 1 3.2 1.1 4.3.9.7-.1 1.8-.7 2.1-1.5.3-.7.3-1.4.2-1.5-.1-.2-.4-.3-.7-.4Z"/></svg>
      </span>
      <div><p class="eyebrow">WHATSAPP</p><h2>Acceso directo</h2><p class="muted">Usa el número configurado en Configuración general y muestra el icono oficial de WhatsApp.</p></div>
    </div>
    <label class="toggle-row"><input type="checkbox" name="floating_whatsapp_enabled" <?=setting('floating_whatsapp_enabled','1')==='1'?'checked':''?>><span>Mostrar botón flotante de WhatsApp</span></label>
    <div class="form-grid">
      <label>Posición<select name="floating_whatsapp_position"><?php foreach($positions as $key=>$label):?><option value="<?=h($key)?>" <?=setting('floating_whatsapp_position','bottom-right')===$key?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label>
      <label>Orden cuando comparte esquina<input type="number" min="1" max="20" name="floating_whatsapp_order" value="<?=h(setting('floating_whatsapp_order','1'))?>"><small>1 aparece primero; un número mayor se separa después.</small></label>
    </div>
  </section>

  <section class="panel widget-config-card">
    <div class="widget-card-head">
      <span class="widget-preview-icon external-preview">⌁</span>
      <div><p class="eyebrow">WIDGET EXTERNO</p><h2>Chat o integración personalizada</h2><p class="muted">Pega aquí el código que te entregue tu proveedor, por ejemplo NIVO Chat Web u otro widget.</p></div>
    </div>
    <label class="toggle-row"><input type="checkbox" name="floating_widget_enabled" <?=setting('floating_widget_enabled','0')==='1'?'checked':''?>><span>Mostrar widget externo</span></label>
    <div class="form-grid">
      <label>Nombre interno<input name="floating_widget_label" value="<?=h(setting('floating_widget_label','Chat'))?>"></label>
      <label>Posición<select name="floating_widget_position"><?php foreach($positions as $key=>$label):?><option value="<?=h($key)?>" <?=setting('floating_widget_position','bottom-left')===$key?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label>
      <label>Orden cuando comparte esquina<input type="number" min="1" max="20" name="floating_widget_order" value="<?=h(setting('floating_widget_order','2'))?>"></label>
      <label class="full">Código del widget<textarea class="code-area" name="floating_widget_code" spellcheck="false" placeholder="<!-- Pega aquí el snippet del proveedor -->"><?=h(setting('floating_widget_code',''))?></textarea><small>Solo un administrador debe pegar código de proveedores confiables. El CMS lo insertará en el sitio público cuando el widget esté habilitado.</small></label>
    </div>
  </section>

  <div class="full form-actions widget-savebar"><button type="submit">Guardar widgets</button><span class="muted">Puedes mostrar WhatsApp, el widget externo o ambos al mismo tiempo.</span></div>
</form>
<?php require __DIR__.'/_footer.php'; ?>
