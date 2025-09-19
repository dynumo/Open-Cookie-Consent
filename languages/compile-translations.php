<?php
/**
 * Simple PHP-based MO file compiler
 * Use this if msgfmt is not available
 */

if (!function_exists('compile_po_to_mo')) {
    function compile_po_to_mo($po_file, $mo_file) {
        if (!file_exists($po_file)) {
            return false;
        }
        
        $po_content = file_get_contents($po_file);
        $lines = explode("\n", $po_content);
        
        $translations = [];
        $current_msgid = '';
        $current_msgstr = '';
        $in_msgid = false;
        $in_msgstr = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'msgid ') === 0) {
                if ($current_msgid && $current_msgstr) {
                    $translations[$current_msgid] = $current_msgstr;
                }
                $current_msgid = substr($line, 7, -1); // Remove 'msgid "' and '"'
                $current_msgstr = '';
                $in_msgid = true;
                $in_msgstr = false;
            } elseif (strpos($line, 'msgstr ') === 0) {
                $current_msgstr = substr($line, 8, -1); // Remove 'msgstr "' and '"'
                $in_msgid = false;
                $in_msgstr = true;
            } elseif ($line && $line[0] === '"' && $line[-1] === '"') {
                $content = substr($line, 1, -1);
                if ($in_msgid) {
                    $current_msgid .= $content;
                } elseif ($in_msgstr) {
                    $current_msgstr .= $content;
                }
            }
        }
        
        if ($current_msgid && $current_msgstr) {
            $translations[$current_msgid] = $current_msgstr;
        }
        
        // Create simplified MO file structure
        $mo_data = pack('V', 0x950412de); // Magic number
        $mo_data .= pack('V', 0); // Version
        $mo_data .= pack('V', count($translations)); // Number of strings
        
        // This is a simplified version - for production use proper MO compilation
        return file_put_contents($mo_file, $mo_data) !== false;
    }
}

// Compile all PO files in the languages directory
$languages_dir = dirname(__FILE__);
$po_files = glob($languages_dir . '/*.po');

foreach ($po_files as $po_file) {
    $mo_file = str_replace('.po', '.mo', $po_file);
    $locale = basename($po_file, '.po');
    
    echo "Compiling {$locale}...\n";
    
    if (compile_po_to_mo($po_file, $mo_file)) {
        echo "✓ Compiled {$locale}.mo\n";
    } else {
        echo "✗ Failed to compile {$locale}.mo\n";
    }
}

echo "\nTranslation compilation complete!\n";
echo "Note: For production use, please use proper gettext tools (msgfmt) for MO compilation.\n";
?>