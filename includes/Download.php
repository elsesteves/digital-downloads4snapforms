<?php

namespace DigitalDownloads4SnapForms;

class Download {
    private $config = [];

    // Construct with per-form config array
    public function __construct(array $config) {
        $this->config = $config;
    }

    // Load all form configs from a JSON file
    public static function loadFormsConfig($configPath) {
        if (!file_exists($configPath)) {
            throw new \Exception('Digital Downloads config file not found: ' . $configPath);
        }

        $json = file_get_contents($configPath);
        $config = json_decode($json, true);
        if (!is_array($config)) {
            throw new \Exception('Invalid Digital Downloads config JSON');
        }
        
        if (isset($config['forms']) && is_array($config['forms'])) {
            $forms = $config['forms'];
            $indexed = [];
            foreach ($forms as $form) {
                if (!is_array($form) || !isset($form['id_form'])) {
                    throw new \Exception('Each form entry must include an id_form');
                }
                $indexed[(string)$form['id_form']] = $form;
            }
            return $indexed;
        }

        //No matching forms found
        return null;
    }

    
    public function serveFile() {
        $filePath = $this->config['download']['url'] ?? '';
        $fileName = $this->config['download']['name'] ?? '';

        if (empty($filePath) || !filter_var($filePath, FILTER_VALIDATE_URL)) {
            return new \WP_Error('dgdownload_file_not_found', 'File not found', []);
        }

        $isRemote = (bool) preg_match('/^https?:\/\//i', $filePath);
        $path = parse_url($filePath, PHP_URL_PATH);
        $ext = '';
        if (is_string($path)) {
            $extArr = explode('.', $path);
            $ext = end($extArr) ?: '';
        }
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        $dispName = trim($safeBase) !== '' ? ($ext ? ($safeBase.'.'.$ext) : $safeBase) : basename($path ?? 'download');

        // Determine MIME and optional content length for remote URLs via headers
        $mime = 'application/octet-stream';
        $contentLength = null;
        if ($isRemote) {
            $headers = @get_headers($filePath, 1);
            if ($headers) {
                if (isset($headers['Content-Type'])) {
                    $ctype = is_array($headers['Content-Type']) ? end($headers['Content-Type']) : $headers['Content-Type'];
                    if (is_string($ctype) && $ctype !== '') $mime = $ctype;
                }
                if (isset($headers['Content-Length'])) {
                    $clen = is_array($headers['Content-Length']) ? end($headers['Content-Length']) : $headers['Content-Length'];
                    if (is_string($clen) || is_int($clen)) $contentLength = $clen;
                }
            }
        } else {
            // Local file path: use finfo and filesize
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = @finfo_file($finfo, $filePath);
                    if (is_string($detected) && $detected !== '') $mime = $detected;
                    finfo_close($finfo);
                }
            }
            if (file_exists($filePath)) {
                $sz = @filesize($filePath);
                if ($sz !== false) $contentLength = $sz;
            }
        }

        if (function_exists('nocache_headers')) nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        if ($contentLength) header('Content-Length: ' . $contentLength);
        header('Content-Disposition: attachment; filename="' . $dispName . '"');

        // Stream file (supports allow_url_fopen for remote URLs)
        readfile($filePath);
    }

}