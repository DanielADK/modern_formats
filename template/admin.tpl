<div class="titrePage"><h2>{'Modern Formats'|@translate}</h2></div>

{if not $MF_CAP_OK}
<div class="errors"><p>{$MF_CAP_REASON}</p></div>
{/if}

<form method="post" action="" id="mfSettings">
  <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
  <fieldset>
    <legend>{'Settings'|@translate}</legend>

    <p>
      <label>{'WebP quality (1-100)'|@translate}
        <input type="number" name="quality" min="1" max="100" value="{$MF_QUALITY}">
      </label>
    </p>
    <p><label><input type="checkbox" name="auto_convert" {$MF_AUTO}> {'Convert new uploads automatically'|@translate}</label></p>
    <p><label><input type="checkbox" name="convert_jpeg" {$MF_JPEG}> {'Convert JPEG'|@translate}</label></p>
    <p><label><input type="checkbox" name="convert_png" {$MF_PNG}> {'Convert PNG'|@translate}</label></p>
    <p><label><input type="checkbox" name="preserve_metadata" {$MF_META}> {'Preserve photo metadata (EXIF/IPTC/XMP)'|@translate}</label></p>
    <p>
      {'Original files'|@translate}:
      <label><input type="radio" name="backup_mode" value="keep" {if $MF_BACKUP eq 'keep'}checked="checked"{/if}> {'Keep a backup'|@translate}</label>
      <label><input type="radio" name="backup_mode" value="delete" {if $MF_BACKUP eq 'delete'}checked="checked"{/if}> {'Delete (save space)'|@translate}</label>
    </p>

    <p><input type="submit" name="submit" value="{'Save Settings'|@translate}"></p>
  </fieldset>
</form>

<fieldset>
  <legend>{'Bulk conversion'|@translate}</legend>
  <p>
    <label>{'Album'|@translate}:
      <select id="mfAlbum">{html_options options=$MF_CATS selected=0}</select>
    </label>
  </p>
  <p id="mfPendingLine">{'Photos pending conversion'|@translate}: <strong id="mfPending">{$MF_PENDING}</strong></p>
  <p>
    <button type="button" id="mfBulkStart"{if not $MF_CAP_OK or $MF_PENDING eq 0} disabled="disabled"{/if}>
      {'Convert existing photos'|@translate}
    </button>
  </p>
  <div id="mfProgressWrap" style="display:none;background:#ddd;height:18px;width:100%;max-width:400px;">
    <div id="mfProgressBar" style="background:#4a8;height:18px;width:0;"></div>
  </div>
  <p id="mfBulkStatus"></p>
  <details id="mfFailures" style="display:none;margin-top:.5em;max-width:600px;">
    <summary><span id="mfFailCount">0</span> {'photos could not be converted'|@translate}</summary>
    <ul id="mfFailList" style="margin:.4em 0 0;font-size:.9em;"></ul>
  </details>
</fieldset>

<script>
window.MF_BULK = {
  wsUrl: "{$MF_WS_URL}",
  token: "{$PWG_TOKEN}",
  total: {$MF_PENDING},
  capOk: {if $MF_CAP_OK}true{else}false{/if},
  i18n: {
    running: "{'Converting...'|@translate}",
    done: "{'Done.'|@translate}",
    failed: "{'Conversion failed.'|@translate}",
    doneErrors: "{'Done, but some photos could not be converted (check logs).'|@translate}",
    reasonError: "{'unreadable or unsupported'|@translate}",
    reasonSkipped: "{'skipped (timed out)'|@translate}"
  }
};
</script>
<script src="{$MF_JS}"></script>
