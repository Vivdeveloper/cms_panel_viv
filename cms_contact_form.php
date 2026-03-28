<?php
/**
 * Contact form shortcode, optional custom fields, SMTP / mail(), and POST handling.
 * Loaded from config.php (after CMS_DATA_DIR is defined).
 */

if (!defined('CMS_DATA_DIR')) {
    return;
}

/** @return list<array{name:string,label:string,type:string,required:bool}> */
function cms_contact_form_default_field_defs(): array {
    return [
        ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
        ['name' => 'mobile', 'label' => 'Mobile Number', 'type' => 'tel', 'required' => true],
        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ['name' => 'product', 'label' => 'Product', 'type' => 'text', 'required' => false],
    ];
}

/** Reserved input names (form internals); not allowed as field `name`. */
function cms_contact_form_reserved_field_names(): array {
    return ['cms_cf_token', 'return_url', 'cms_hp_notes'];
}

/**
 * Normalize user-provided field list (decoded JSON array). Returns null if invalid.
 *
 * @param mixed $parsed
 * @return list<array{name:string,label:string,type:string,required:bool}>|null
 */
function cms_contact_form_normalize_fields_from_user($parsed): ?array {
    if (!is_array($parsed)) {
        return null;
    }
    $n = count($parsed);
    if ($n < 1 || $n > 20) {
        return null;
    }
    $reserved = cms_contact_form_reserved_field_names();
    $out = [];
    $seen = [];
    foreach ($parsed as $row) {
        if (!is_array($row)) {
            return null;
        }
        $name = strtolower(trim((string) ($row['name'] ?? '')));
        if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $name)) {
            return null;
        }
        if (in_array($name, $reserved, true)) {
            return null;
        }
        if (isset($seen[$name])) {
            return null;
        }
        $seen[$name] = true;
        $label = trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($row['label'] ?? '')));
        if ($label === '' || strlen($label) > 200) {
            return null;
        }
        $type = strtolower(trim((string) ($row['type'] ?? 'text')));
        if ($type === 'dropdown') {
            $type = 'select';
        }
        if (!in_array($type, ['text', 'email', 'tel', 'textarea', 'number', 'select'], true)) {
            return null;
        }

        $entry = [
            'name'     => $name,
            'label'    => $label,
            'type'     => $type,
            'required' => !empty($row['required']),
        ];

        if ($type === 'select') {
            $optsIn = $row['options'] ?? [];
            if (!is_array($optsIn) || count($optsIn) < 1 || count($optsIn) > 40) {
                return null;
            }
            $cleanOpts = [];
            foreach ($optsIn as $o) {
                $s = trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) $o));
                if ($s === '' || strlen($s) > 200) {
                    return null;
                }
                $cleanOpts[] = $s;
            }
            if (count($cleanOpts) !== count(array_unique($cleanOpts))) {
                return null;
            }
            $entry['options'] = $cleanOpts;
        }

        if ($type === 'number') {
            foreach (['min' => 'min', 'max' => 'max', 'step' => 'step'] as $jsonKey => $outKey) {
                if (!array_key_exists($jsonKey, $row)) {
                    continue;
                }
                $rawN = $row[$jsonKey];
                if ($rawN === '' || $rawN === null) {
                    continue;
                }
                if (is_string($rawN) && !is_numeric(trim($rawN))) {
                    return null;
                }
                if (!is_numeric($rawN)) {
                    return null;
                }
                $entry[$outKey] = $rawN + 0;
            }
            if (isset($entry['min'], $entry['max']) && $entry['min'] > $entry['max']) {
                return null;
            }
        }

        $out[] = $entry;
    }
    return $out;
}

/**
 * Fields for the public form and CRM column headers.
 *
 * @return list<array{name:string,label:string,type:string,required:bool}>
 */
function cms_contact_form_fields(): array {
    $s = getSiteSettings();
    if (empty($s['contact_form_use_custom'])) {
        return cms_contact_form_default_field_defs();
    }
    $raw = $s['contact_form_fields'] ?? [];
    if (!is_array($raw)) {
        return cms_contact_form_default_field_defs();
    }
    $norm = cms_contact_form_normalize_fields_from_user($raw);
    return $norm !== null ? $norm : cms_contact_form_default_field_defs();
}

/**
 * JSON textarea default in admin: last saved custom list, or built-in defaults.
 *
 * @return list<array{name:string,label:string,type:string,required:bool}>
 */
function cms_contact_form_fields_json_template(): array {
    $s = getSiteSettings();
    $raw = $s['contact_form_fields'] ?? [];
    if (is_array($raw)) {
        $norm = cms_contact_form_normalize_fields_from_user($raw);
        if ($norm !== null) {
            return $norm;
        }
    }
    return cms_contact_form_default_field_defs();
}

function cms_contact_submissions_file(): string {
    return CMS_DATA_DIR . 'contact_submissions.json';
}

/** @return list<string> */
function cms_crm_status_values(): array {
    return ['pending', 'done'];
}

function cms_crm_normalize_status($raw): string {
    $s = strtolower(trim((string) $raw));
    if ($s === 'done') {
        return 'done';
    }
    if (in_array($s, ['new', 'followup', 'pending'], true)) {
        return 'pending';
    }
    return 'pending';
}

function cms_crm_format_lead_date(string $at): string {
    $ts = strtotime($at);
    if ($ts === false) {
        return $at;
    }
    return date('M j, Y · g:i A', $ts);
}

/** @return string Normalized Y-m-d or empty if invalid */
function cms_crm_sanitize_date_ymd(string $s): string {
    $s = trim($s);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return '';
    }
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return '';
    }
    return $s;
}

function cms_crm_row_matches_date_filter(string $at, string $preset, string $from, string $to): bool {
    $preset = strtolower(trim($preset));
    if (!in_array($preset, ['all', 'today', 'custom'], true)) {
        $preset = 'all';
    }
    if ($preset === 'all') {
        return true;
    }
    $ts = strtotime($at);
    if ($ts === false) {
        return false;
    }
    if ($preset === 'today') {
        return date('Y-m-d', $ts) === date('Y-m-d');
    }
    $from = cms_crm_sanitize_date_ymd($from);
    $to = cms_crm_sanitize_date_ymd($to);
    if ($from === '' && $to === '') {
        return true;
    }
    if ($from === '') {
        $from = $to;
    }
    if ($to === '') {
        $to = $from;
    }
    if ($from > $to) {
        $swap = $from;
        $from = $to;
        $to = $swap;
    }
    $start = strtotime($from . ' 00:00:00');
    $end = strtotime($to . ' 23:59:59');
    if ($start === false || $end === false) {
        return true;
    }
    return $ts >= $start && $ts <= $end;
}

function cms_crm_tel_href(string $raw): string {
    $d = preg_replace('/\D+/', '', $raw);
    return $d !== '' ? 'tel:' . $d : '';
}

/**
 * @return list<array{id:string,at:string,ip:string,mail_ok:bool,crm_status:string,fields:array<string,string>}>
 */
function cms_contact_get_submissions(): array {
    $path = cms_contact_submissions_file();
    if (!is_file($path)) {
        return [];
    }
    $j = json_decode((string) file_get_contents($path), true);
    if (!is_array($j)) {
        return [];
    }
    $out = [];
    foreach ($j as $row) {
        if (!is_array($row) || empty($row['id']) || empty($row['at'])) {
            continue;
        }
        $fields = $row['fields'] ?? [];
        if (!is_array($fields)) {
            $fields = [];
        }
        $out[] = [
            'id'         => (string) $row['id'],
            'at'         => (string) $row['at'],
            'ip'         => (string) ($row['ip'] ?? ''),
            'mail_ok'    => !empty($row['mail_ok']),
            'crm_status' => cms_crm_normalize_status($row['crm_status'] ?? 'pending'),
            'fields'     => array_map('strval', $fields),
        ];
    }
    return $out;
}

/**
 * @param list<array{id:string,at:string,ip:string,mail_ok:bool,crm_status:string,fields:array<string,string>}> $rows
 * @return list<array{id:string,at:string,ip:string,mail_ok:bool,crm_status:string,fields:array<string,string>}>
 */
function cms_crm_filter_submissions(array $rows, string $filter, string $q, string $datePreset = 'all', string $dateFrom = '', string $dateTo = ''): array {
    $filter = strtolower(trim($filter));
    if (!in_array($filter, array_merge(['all'], cms_crm_status_values()), true)) {
        $filter = 'all';
    }
    $q = trim($q);
    $out = [];
    foreach ($rows as $r) {
        if ($filter !== 'all' && ($r['crm_status'] ?? 'pending') !== $filter) {
            continue;
        }
        if (!cms_crm_row_matches_date_filter((string) ($r['at'] ?? ''), $datePreset, $dateFrom, $dateTo)) {
            continue;
        }
        if ($q !== '') {
            $hay = strtolower(implode(' ', array_values($r['fields'] ?? [])));
            if (strpos($hay, strtolower($q)) === false) {
                continue;
            }
        }
        $out[] = $r;
    }
    return $out;
}

function cms_crm_update_submission_status(string $id, string $status, bool $onlyIfCurrentPending = false): bool {
    $id = strtolower(preg_replace('/[^a-f0-9]/', '', $id));
    if (strlen($id) < 8) {
        return false;
    }
    $status = cms_crm_normalize_status($status);
    if (!is_dir(CMS_DATA_DIR)) {
        mkdir(CMS_DATA_DIR, 0755, true);
    }
    $path = cms_contact_submissions_file();
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }
    $raw = stream_get_contents($fp);
    $list = [];
    if ($raw !== false && trim($raw) !== '') {
        $d = json_decode($raw, true);
        if (is_array($d)) {
            $list = $d;
        }
    }
    $found = false;
    foreach ($list as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $rid = strtolower(preg_replace('/[^a-f0-9]/', '', (string) ($row['id'] ?? '')));
        if ($rid === $id) {
            $cur = cms_crm_normalize_status((string) ($row['crm_status'] ?? 'pending'));
            if ($onlyIfCurrentPending && $cur === 'done') {
                flock($fp, LOCK_UN);
                fclose($fp);
                return false;
            }
            $list[$i]['crm_status'] = $status;
            $found = true;
            break;
        }
    }
    if ($found) {
        $out = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $out);
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    return $found;
}

/**
 * @param array{fields:array<string,string>,ip:string,mail_ok:bool} $entry
 */
function cms_contact_append_submission(array $entry): void {
    if (!is_dir(CMS_DATA_DIR)) {
        mkdir(CMS_DATA_DIR, 0755, true);
    }
    $path = cms_contact_submissions_file();
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }
    $raw = stream_get_contents($fp);
    $list = [];
    if ($raw !== false && trim($raw) !== '') {
        $d = json_decode($raw, true);
        if (is_array($d)) {
            $list = $d;
        }
    }
    $row = [
        'id'          => bin2hex(random_bytes(8)),
        'at'          => gmdate('c'),
        'ip'          => $entry['ip'],
        'mail_ok'     => !empty($entry['mail_ok']),
        'crm_status'  => 'pending',
        'fields'      => $entry['fields'],
    ];
    array_unshift($list, $row);
    $list = array_slice($list, 0, 500);
    $out = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $out);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function cms_contact_form_to_email(): string {
    $e = trim((string) (getSiteSettings()['contact_form_to_email'] ?? ''));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
}

function cms_contact_form_subject_template(): string {
    $s = trim((string) (getSiteSettings()['contact_form_subject'] ?? ''));
    return $s !== '' ? $s : 'New contact from {site}';
}

function cms_contact_form_ensure_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['cms_cf_t']) || !is_string($_SESSION['cms_cf_t']) || strlen($_SESSION['cms_cf_t']) < 16) {
        $_SESSION['cms_cf_t'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cms_cf_t'];
}

/**
 */
function cms_apply_page_shortcodes(string $html, string $returnUrl): string {
    return preg_replace_callback('/\[cms_contact_form([^\]]*)\]/i', static function (array $m) use ($returnUrl) {
        $attrs = $m[1] ?? '';
        $title = null;
        if (preg_match('/\btitle\s*=\s*"([^"]*)"/u', $attrs, $tm)) { $title = $tm[1]; }
        elseif (preg_match("/\btitle\s*=\s*'([^']*)'/u", $attrs, $tm)) { $title = $tm[1]; }
        return cms_render_contact_form_html($returnUrl, $title);
    }, $html);
}

function cms_contact_form_safe_return_url(string $raw, string $fallback): string {
    $raw = trim($raw);
    if ($raw === '') {
        return $fallback;
    }
    $base = rtrim(cms_site_url(), '/');
    if (strpos($raw, $base) === 0) {
        return $raw;
    }
    if ($raw[0] === '/' && (!isset($raw[1]) || $raw[1] !== '/')) {
        return $base . $raw;
    }
    return $fallback;
}

/** Inline notice after redirect from contact_submit.php */
function cms_contact_flash_message_html(): string {
    if (empty($_GET['contact_msg'])) {
        return '';
    }
    $code = strtolower(preg_replace('/[^a-z_]/', '', (string) $_GET['contact_msg']));
    $map = [
        'sent'     => ['ok', 'Thanks — your message was sent.'],
        'csrf'     => ['err', 'Your session expired. Refresh the page and try again.'],
        'rate'     => ['err', 'Too many requests. Please wait a few minutes.'],
        'required' => ['err', 'Please fill in all required fields.'],
        'email'    => ['err', 'Please enter a valid email address.'],
        'config'   => ['err', 'This form is not configured.'],
        'sendfail' => ['err', 'The message could not be sent. Try again later or contact us directly.'],
    ];
    if (!isset($map[$code])) {
        return '';
    }
    [$kind, $text] = $map[$code];
    $cls = 'cms-contact-flash cms-contact-flash--' . ($kind === 'ok' ? 'ok' : 'err');
    
    ob_start(); ?>
    <div id="cms-contact-flash" class="<?php echo $cls; ?>" role="status">
        <?php echo ($kind === 'ok' ? '✓ ' : '⚠ '); ?> <?php echo cms_escape($text); ?>
    </div>
    <script>
    setTimeout(function() {
        var el = document.getElementById('cms-contact-flash');
        if (el) {
            el.style.transition = 'opacity 1s ease, transform 1s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(10px)';
            setTimeout(function() { el.remove(); }, 1000);
        }
    }, 5000);
    </script>
    <?php
    return ob_get_clean();
}

function cms_render_contact_form_html(string $returnUrl, ?string $heading = null): string {
    $to = cms_contact_form_to_email();
    $noRecipient = ($to === '');
    $token = cms_contact_form_ensure_token();
    if ($token === '') {
        return '<div class="cms-contact-form cms-contact-form--disabled"><p class="cms-contact-form__notice">Session could not be started; the form cannot be shown.</p></div>';
    }
    $fields = cms_contact_form_fields();
    $action = cms_escape(cms_url('contact_submit.php'));
    $ret = cms_escape(cms_contact_form_safe_return_url($returnUrl, cms_home_url()) . '#cms-contact-form');
    $tok = cms_escape($token);

    ob_start();
    ?>
<div id="cms-contact-form" class="cms-contact-form<?php echo $noRecipient ? ' cms-contact-form--needs-config' : ''; ?>">
    <?php if ($heading !== null && $heading !== ''): ?>
    <h3 class="cms-contact-form__title"><?php echo cms_escape($heading); ?></h3>
    <?php endif; ?>
    <?php if ($noRecipient): ?>
    <p class="cms-contact-form__notice cms-contact-form__notice--warn">Set <strong>Send submissions to</strong> under <strong>Admin → Contact form</strong> to enable sending. You can still see the fields below.</p>
    <?php endif; ?>
    <form class="cms-contact-form__form" method="post" action="<?php echo $action; ?>">
        <input type="hidden" name="cms_cf_token" value="<?php echo $tok; ?>">
        <input type="hidden" name="return_url" value="<?php echo $ret; ?>">
        <div class="cms-contact-form__hp" aria-hidden="true">
            <label for="cms-hp-notes">Leave empty</label>
            <input type="text" name="cms_hp_notes" id="cms-hp-notes" value="" tabindex="-1" autocomplete="off">
        </div>
        <?php foreach ($fields as $f):
            $id = 'cf-' . preg_replace('/[^a-z0-9_-]/', '', $f['name']);
            $nm = cms_escape($f['name']);
            $lb = cms_escape($f['label']);
            $req = !empty($f['required']);
            $ftype = $f['type'] ?? 'text';
            $placeholder = $lb . ($req ? ' *' : '');
            ?>
        <div class="cms-contact-form__field cms-contact-form__field--<?php echo $nm; ?>">
            <?php if ($ftype === 'textarea'): ?>
            <textarea class="cms-contact-form__input cms-contact-form__textarea" id="<?php echo cms_escape($id); ?>" name="<?php echo $nm; ?>" placeholder="<?php echo $placeholder; ?>" rows="4" <?php echo $req ? 'required' : ''; ?>></textarea>
            <?php elseif ($ftype === 'select' && !empty($f['options']) && is_array($f['options'])): ?>
            <select class="cms-contact-form__input cms-contact-form__select" id="<?php echo cms_escape($id); ?>" name="<?php echo $nm; ?>" <?php echo $req ? 'required' : ''; ?>>
                <option value="" disabled selected><?php echo $placeholder; ?></option>
                <?php foreach ($f['options'] as $opt):
                    $ov = cms_escape((string) $opt);
                    ?>
                <option value="<?php echo $ov; ?>"><?php echo $ov; ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input class="cms-contact-form__input" type="<?php echo ($ftype === 'email' ? 'email' : ($ftype === 'tel' ? 'tel' : 'text')); ?>" id="<?php echo cms_escape($id); ?>" name="<?php echo $nm; ?>" placeholder="<?php echo $placeholder; ?>" <?php echo $req ? 'required' : ''; ?>>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="cms-contact-form__submit"<?php echo $noRecipient ? ' disabled aria-disabled="true" title="Add a recipient email in Admin → Contact form"' : ''; ?>>Send</button>

        <div id="cms-cf-status-wrap">
            <?php echo cms_contact_flash_message_html(); ?>
        </div>
    </form>
    <script>
    (function(){
        var cf = document.querySelector('#cms-contact-form form');
        if (!cf) return;
        cf.addEventListener('submit', function(e){
            e.preventDefault();
            if (e.target.hasAttribute('data-submitting')) return;
            
            var wrap = document.getElementById('cms-cf-status-wrap');
            
            // Client-side validation check
            if (!cf.checkValidity()) {
                cf.reportValidity(); // Shows native popups
                wrap.innerHTML = '<div class="cms-contact-flash cms-contact-flash--err">⚠ Please fill in all required fields.</div>';
                return;
            }
            
            var btn = cf.querySelector('button[type="submit"]');
            var oldBtnText = btn.innerHTML;
            wrap.innerHTML = ''; // Clear old messages
            
            btn.innerHTML = 'Sending...';
            btn.setAttribute('data-submitting', '1');
            btn.style.opacity = '0.7';
            
            var fd = new FormData(cf);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', cf.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                btn.innerHTML = oldBtnText;
                btn.removeAttribute('data-submitting');
                btn.style.opacity = '1';
                if (xhr.status === 200) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.ok) { cf.reset(); }
                        // Clean URL for fetch
                        var cleanUrl = window.location.href.split('#')[0];
                        var finalUrl = cleanUrl + (cleanUrl.indexOf('?') > -1 ? '&' : '?') + 'contact_msg=' + resp.msg;
                        fetch(finalUrl)
                        .then(r => r.text())
                        .then(html => {
                            var parser = new DOMParser();
                            var doc = parser.parseFromString(html, 'text/html');
                            var newStatus = doc.getElementById('cms-contact-flash');
                            if (newStatus) { wrap.innerHTML = newStatus.outerHTML; }
                            else { window.location.href = finalUrl + '#cms-contact-form'; } // Fallback
                        }).catch(function(){ window.location.href = finalUrl + '#cms-contact-form'; });
                    } catch(e) { window.location.reload(); }
                } else { wrap.innerHTML = '<div class="cms-contact-flash cms-contact-flash--err">Server error. Please try again.</div>'; }
            };
            xhr.onerror = function() { window.location.reload(); };
            xhr.send(fd);
        });
    })();
    </script>
</div>
    <?php
    return (string) ob_get_clean();
}

function cms_contact_rate_check(string $ip): bool {
    if (!is_dir(CMS_DATA_DIR)) {
        return true;
    }
    $dir = CMS_DATA_DIR . 'contact_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return true;
    }
    $key = md5($ip);
    $path = $dir . '/' . $key . '.json';
    $window = 600;
    $max = 8;
    $now = time();
    $data = ['t' => $now, 'n' => 1];
    if (is_file($path)) {
        $j = json_decode((string) file_get_contents($path), true);
        if (is_array($j) && isset($j['t'], $j['n'])) {
            if ($now - (int) $j['t'] < $window) {
                if ((int) $j['n'] >= $max) {
                    return false;
                }
                $data = ['t' => (int) $j['t'], 'n' => (int) $j['n'] + 1];
            }
        }
    }
    @file_put_contents($path, json_encode($data));
    return true;
}

function cms_smtp_read_response($fp): string {
    $all = '';
    while (true) {
        $line = @fgets($fp, 8192);
        if ($line === false) {
            break;
        }
        $all .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $all;
}

function cms_smtp_expect($fp, array $codes): bool {
    $r = cms_smtp_read_response($fp);
    $code = (int) substr(trim($r), 0, 3);
    return in_array($code, $codes, true);
}

function cms_smtp_cmd($fp, string $line, array $codes): bool {
    fwrite($fp, $line . "\r\n");
    return cms_smtp_expect($fp, $codes);
}

/** @param array $s getSiteSettings() */
function cms_smtp_send_plain(array $s, string $to, string $subject, string $body, string $replyTo): bool {
    $host = trim((string) ($s['smtp_host'] ?? ''));
    $port = (int) ($s['smtp_port'] ?? 587);
    if ($port <= 0 || $port > 65535) {
        $port = 587;
    }
    $enc = strtolower(trim((string) ($s['smtp_encryption'] ?? 'tls')));
    if (!in_array($enc, ['none', 'tls', 'ssl'], true)) {
        $enc = 'tls';
    }
    $user = (string) ($s['smtp_user'] ?? '');
    $pass = (string) ($s['smtp_pass'] ?? '');
    $fromEmail = trim((string) ($s['smtp_from_email'] ?? ''));
    $fromName = trim((string) ($s['smtp_from_name'] ?? ''));
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $remote = ($enc === 'ssl') ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 25, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return false;
    }
    stream_set_timeout($fp, 25);
    if (!cms_smtp_expect($fp, [220])) {
        fclose($fp);
        return false;
    }
    $ehloHost = 'localhost';
    if (!cms_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250])) {
        fclose($fp);
        return false;
    }
    if ($enc === 'tls') {
        if (!cms_smtp_cmd($fp, 'STARTTLS', [220])) {
            fclose($fp);
            return false;
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return false;
        }
        if (!cms_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250])) {
            fclose($fp);
            return false;
        }
    }
    if ($user !== '' && $pass !== '') {
        if (!cms_smtp_cmd($fp, 'AUTH LOGIN', [334])) {
            fclose($fp);
            return false;
        }
        if (!cms_smtp_cmd($fp, base64_encode($user), [334])) {
            fclose($fp);
            return false;
        }
        if (!cms_smtp_cmd($fp, base64_encode($pass), [235])) {
            fclose($fp);
            return false;
        }
    }
    if (!cms_smtp_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250])) {
        fclose($fp);
        return false;
    }
    if (!cms_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251])) {
        fclose($fp);
        return false;
    }
    if (!cms_smtp_cmd($fp, 'DATA', [354])) {
        fclose($fp);
        return false;
    }
    $subjMime = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
        : '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . ($fromName !== '' ? sprintf('"%s" <%s>', str_replace(['"', "\r", "\n"], '', $fromName), $fromEmail) : $fromEmail),
        'To: <' . $to . '>',
        'Subject: ' . $subjMime,
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $dotBody = str_replace("\r\n", "\n", $body);
    $dotBody = str_replace("\n", "\r\n", $dotBody);
    $dotBody = preg_replace('/^\./m', '..', $dotBody);
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $dotBody . "\r\n.";
    fwrite($fp, $payload . "\r\n");
    $ok = cms_smtp_expect($fp, [250]);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

/**
 * Sends contact notification: SMTP when enabled + host set; otherwise PHP mail().
 * mail() path: set From to a domain mailbox in admin (e.g. info@yoursite.com); Reply-To is the visitor email when present.
 */
function cms_contact_send_mail(string $to, string $subject, string $bodyText, string $replyTo): bool {
    $s = getSiteSettings();
    if (!empty($s['smtp_enabled']) && trim((string) ($s['smtp_host'] ?? '')) !== '') {
        return cms_smtp_send_plain($s, $to, $subject, $bodyText, $replyTo);
    }
    $from = trim((string) ($s['smtp_from_email'] ?? ''));
    $fromName = trim((string) ($s['smtp_from_name'] ?? ''));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/^www\./i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $from = 'noreply@' . $host;
    }
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if ($fromName !== '') {
        $headers[] = 'From: ' . sprintf('"%s" <%s>', str_replace(['"', "\r", "\n"], '', $fromName), $from);
    } else {
        $headers[] = 'From: ' . $from;
    }
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $subj = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
        : '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $subj, $bodyText, implode("\r\n", $headers));
}

/**
 * Handle POST from public contact form; redirects with contact_msg= query param.
 */
function cms_contact_handle_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Location: ' . cms_home_url());
        exit;
    }
    $ret = cms_contact_form_safe_return_url((string) ($_POST['return_url'] ?? ''), cms_home_url());
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $redirect = function (string $msg) use ($ret, $isAjax) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => ($msg === 'sent'), 'msg' => $msg]);
            exit;
        }
        $sep = strpos($ret, '?') !== false ? '&' : '?';
        header('Location: ' . $ret . $sep . 'contact_msg=' . rawurlencode($msg));
        exit;
    };

    if (trim((string) ($_POST['cms_hp_notes'] ?? '')) !== '') {
        $redirect('sent');
    }

    $tok = (string) ($_POST['cms_cf_token'] ?? '');
    if ($tok === '' || !isset($_SESSION['cms_cf_t']) || !hash_equals((string) $_SESSION['cms_cf_t'], $tok)) {
        $redirect('csrf');
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0');
    if (!cms_contact_rate_check($ip)) {
        $redirect('rate');
    }

    $to = cms_contact_form_to_email();
    if ($to === '') {
        $redirect('config');
    }

    $fields = cms_contact_form_fields();
    $lines = [];
    $replyTo = '';
    $fieldData = [];
    foreach ($fields as $f) {
        $key = $f['name'];
        $ftype = $f['type'] ?? 'text';
        $val = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';

        if ($ftype === 'select') {
            $opts = $f['options'] ?? [];
            if (!is_array($opts)) {
                $opts = [];
            }
            if ($val === '') {
                if (!empty($f['required'])) {
                    $redirect('required');
                }
            } elseif (!in_array($val, $opts, true)) {
                $redirect('required');
            }
        } elseif ($ftype === 'number') {
            if ($val === '') {
                if (!empty($f['required'])) {
                    $redirect('required');
                }
            } elseif (!is_numeric($val)) {
                $redirect('required');
            } else {
                $num = 0 + $val;
                if (isset($f['min']) && is_numeric($f['min']) && $num < (float) $f['min']) {
                    $redirect('required');
                }
                if (isset($f['max']) && is_numeric($f['max']) && $num > (float) $f['max']) {
                    $redirect('required');
                }
                $val = (string) $val;
            }
        } else {
            if (!empty($f['required']) && $val === '') {
                $redirect('required');
            }
            if ($ftype === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $redirect('email');
            }
        }

        if ($replyTo === '' && $ftype === 'email' && $val !== '' && filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $replyTo = $val;
        }
        $fieldData[$key] = $val;
        $lines[] = $f['label'] . ': ' . $val;
    }

    $subjTpl = cms_contact_form_subject_template();
    $subject = str_replace('{site}', cms_brand(), $subjTpl);
    $body = implode("\n", $lines) . "\n\n— " . cms_brand() . ' / ' . cms_site_url();

    $ok = cms_contact_send_mail($to, $subject, $body, $replyTo);
    cms_contact_append_submission([
        'ip'      => $ip,
        'mail_ok' => $ok,
        'fields'  => $fieldData,
    ]);
    $_SESSION['cms_cf_t'] = bin2hex(random_bytes(16));
    $redirect($ok ? 'sent' : 'sendfail');
}

/**
 * Merge contact form + SMTP settings into site_settings.json (admin only).
 * When $fieldsErr is set to "invalid", custom fields were left unchanged (other settings saved).
 */
function cms_save_contact_form_settings(array $post, ?string &$fieldsErr = null): void {
    $fieldsErr = '';
    $cur = getSiteSettings();
    $cur['contact_form_to_email'] = trim((string) ($post['contact_form_to_email'] ?? ''));
    $cur['contact_form_subject'] = trim((string) ($post['contact_form_subject'] ?? 'New contact from {site}'));
    if ($cur['contact_form_subject'] === '') {
        $cur['contact_form_subject'] = 'New contact from {site}';
    }

    $wantCustom = !empty($post['contact_form_use_custom']) && $post['contact_form_use_custom'] === '1';
    $jsonRaw = (string) ($post['contact_form_fields_json'] ?? '');
    $parsed = json_decode($jsonRaw, true);
    $norm = cms_contact_form_normalize_fields_from_user($parsed);

    if ($wantCustom) {
        if ($norm === null) {
            $fieldsErr = 'invalid';
        } else {
            $cur['contact_form_use_custom'] = true;
            $cur['contact_form_fields'] = $norm;
        }
    } else {
        $cur['contact_form_use_custom'] = false;
        if ($norm !== null) {
            $cur['contact_form_fields'] = $norm;
        }
    }

    $cur['smtp_enabled'] = !empty($post['smtp_enabled']) && $post['smtp_enabled'] === '1';
    $cur['smtp_host'] = trim((string) ($post['smtp_host'] ?? ''));
    $cur['smtp_port'] = (int) ($post['smtp_port'] ?? 587);
    if ($cur['smtp_port'] <= 0) {
        $cur['smtp_port'] = 587;
    }
    $enc = strtolower(trim((string) ($post['smtp_encryption'] ?? 'tls')));
    $cur['smtp_encryption'] = in_array($enc, ['none', 'tls', 'ssl'], true) ? $enc : 'tls';
    $cur['smtp_user'] = trim((string) ($post['smtp_user'] ?? ''));
    $newPass = (string) ($post['smtp_pass'] ?? '');
    if ($newPass !== '') {
        $cur['smtp_pass'] = $newPass;
    } elseif (!isset($cur['smtp_pass'])) {
        $cur['smtp_pass'] = '';
    }
    $cur['smtp_from_email'] = trim((string) ($post['smtp_from_email'] ?? ''));
    $cur['smtp_from_name'] = trim((string) ($post['smtp_from_name'] ?? ''));

    if (!is_dir(CMS_DATA_DIR)) {
        mkdir(CMS_DATA_DIR, 0755, true);
    }
    file_put_contents(CMS_DATA_DIR . 'site_settings.json', json_encode($cur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    cms_invalidate_site_settings();
}
