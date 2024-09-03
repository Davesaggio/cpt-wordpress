<?php 
function duplicate_menu_with_translations($original_menu_name, $new_language_code) {
    // Trova il menu originale tramite il suo nome
    $original_menu = wp_get_nav_menu_object($original_menu_name);
    if (!$original_menu) {
        return "Il menu originale non è stato trovato.";
    }

    // Crea il nuovo nome del menu aggiungendo il codice della lingua
    $new_menu_name = $original_menu->name . ' - ' . strtoupper($new_language_code);

    // Verifica se un menu con lo stesso nome esiste già
    $existing_menu = wp_get_nav_menu_object($new_menu_name);
    if ($existing_menu) {
        return "Un menu con il nome '$new_menu_name' esiste già.";
    }

    // Crea il nuovo menu
    $new_menu_id = wp_create_nav_menu($new_menu_name);
    if (is_wp_error($new_menu_id)) {
        $error_message = $new_menu_id->get_error_message();
        return "Errore nella creazione del nuovo menu: $error_message";
    }

    // Ottieni gli elementi del menu originale
    $menu_items = wp_get_nav_menu_items($original_menu->term_id);
    if (!$menu_items) {
        return "Nessun elemento trovato nel menu originale.";
    }

    // Creare un array per tenere traccia delle relazioni tra elementi originali e duplicati
    $menu_item_map = [];

    // Duplica ogni elemento del menu
    foreach ($menu_items as $menu_item) {
        $translated_object_id = null;

        if ($menu_item->type == 'custom') {
            $menu_item_data = array(
                'menu-item-object-id' => $menu_item->object_id,
                'menu-item-object' => $menu_item->object,
                'menu-item-parent-id' => isset($menu_item_map[$menu_item->menu_item_parent]) ? $menu_item_map[$menu_item->menu_item_parent] : 0,
                'menu-item-position' => $menu_item->menu_order,
                'menu-item-type' => $menu_item->type,
                'menu-item-title' => $menu_item->title,
                'menu-item-url' => $menu_item->url,
                'menu-item-description' => $menu_item->description,
                'menu-item-attr-title' => $menu_item->attr_title,
                'menu-item-target' => $menu_item->target,
                'menu-item-classes' => implode(' ', $menu_item->classes),
                'menu-item-xfn' => $menu_item->xfn,
                'menu-item-status' => 'publish'
            );
        } else {
            $translated_object_id = pll_get_post($menu_item->object_id, $new_language_code);

            if ($translated_object_id) {
                $menu_item_data = array(
                    'menu-item-object-id' => $translated_object_id,
                    'menu-item-object' => $menu_item->object,
                    'menu-item-parent-id' => isset($menu_item_map[$menu_item->menu_item_parent]) ? $menu_item_map[$menu_item->menu_item_parent] : 0,
                    'menu-item-position' => $menu_item->menu_order,
                    'menu-item-type' => $menu_item->type,
                    'menu-item-title' => get_the_title($translated_object_id),
                    'menu-item-url' => get_permalink($translated_object_id),
                    'menu-item-description' => $menu_item->description,
                    'menu-item-attr-title' => $menu_item->attr_title,
                    'menu-item-target' => $menu_item->target,
                    'menu-item-classes' => implode(' ', $menu_item->classes),
                    'menu-item-xfn' => $menu_item->xfn,
                    'menu-item-status' => 'publish'
                );
            } else {
                continue;
            }
        }

        $new_menu_item_id = wp_update_nav_menu_item($new_menu_id, 0, $menu_item_data);
        $menu_item_map[$menu_item->ID] = $new_menu_item_id;
    }

    pll_set_term_language($new_menu_id, $new_language_code);

    return "Menu duplicato con successo per la lingua $new_language_code!";
}

function duplicate_menu_admin_page() {
    add_menu_page(
        'Duplica Menu Polylang',
        'Duplica Menu Polylang',
        'manage_options',
        'duplicate-menu-polylang',
        'duplicate_menu_admin_page_content',
        'dashicons-admin-generic'
    );
}

function duplicate_menu_admin_page_content() {
    $languages = pll_languages_list();
    $menus = wp_get_nav_menus();

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['duplicate_menu_language']) && isset($_POST['original_menu_name'])) {
        $selected_language = sanitize_text_field($_POST['duplicate_menu_language']);
        $original_menu_name = sanitize_text_field($_POST['original_menu_name']);
        $result = duplicate_menu_with_translations($original_menu_name, $selected_language);
        echo "<div class='updated notice'><p>$result</p></div>";
    }

    echo '<div class="wrap">';
    echo '<h1>Duplica Menu Polylang</h1>';
    echo '<form method="post">';
    echo '<p>Seleziona il menu principale da duplicare:</p>';
    echo '<select name="original_menu_name">';
    
    foreach ($menus as $menu) {
        echo '<option value="' . esc_attr($menu->slug) . '">' . esc_html($menu->name) . '</option>';
    }
    
    echo '</select>';
    echo '<p>Seleziona la lingua per duplicare il menu dalla lingua principale:</p>';

    foreach ($languages as $language) {
        echo '<button type="submit" name="duplicate_menu_language" value="' . esc_attr($language) . '" class="button button-primary">Duplica per ' . strtoupper($language) . '</button>';
    }

    echo '</form>';
    echo '</div>';
}

add_action('admin_menu', 'duplicate_menu_admin_page');
