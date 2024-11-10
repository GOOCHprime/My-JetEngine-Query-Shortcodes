<?php
/**
 * Plugin Name: My JetEngine Query Shortcodes
 * Plugin URI: https://legworkmedia.ca
 * Description: Provides shortcodes to execute JetEngine queries and display results in various formats.
 * Version: 1.1.0
 * Author: Luca Gallucci
 * Author URI: https://legworkmedia.ca
 * License: GNU General Public License v3.0
 * Text Domain: my-jetengine-query-shortcodes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Fetches the manual SQL query string from the JetEngine Query Builder stored in the wp_jet_post_types table.
 *
 * @param int $query_id The unique identifier for the saved query in JetEngine.
 * @return string|bool Returns the SQL query string if found and valid, or false if not found or invalid.
 */
function myjeqs_fetch_jetengine_query_by_id( $query_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'jet_post_types';
    $sql = $wpdb->prepare(
        "SELECT args FROM $table_name WHERE id = %d AND status = 'query'",
        $query_id
    );
    $result = $wpdb->get_var( $sql );

    if ( ! empty( $result ) ) {
        $args = maybe_unserialize( $result );
        if ( is_array( $args ) && isset( $args['sql']['manual_query'] ) ) {
            return $args['sql']['manual_query'];
        }
    }

    return false;
}

/**
 * A shortcode function to execute a JetEngine Query and return specified columns from a single result
 * in various formats (text, json, or html).
 *
 * Usage: [my_jet_query query_id="123" columns="package_title,package_id" format="text"]
 *
 * @param array $atts User-defined attributes in the shortcode.
 * @return string Output from the executed query or an error message.
 */
function myjeqs_custom_jet_query_shortcode( $atts ) {
    $attributes = shortcode_atts( [
        'query_id' => 0,
        'columns'  => 'package_title',
        'format'   => 'text',
    ], $atts );

    if ( empty( $attributes['query_id'] ) ) {
        return "Error: query_id is required.";
    }

    $sql_query = myjeqs_fetch_jetengine_query_by_id( $attributes['query_id'] );
    if ( ! $sql_query ) {
        return "Query not found or invalid.";
    }

    global $wpdb;
    $current_id = get_the_ID();
    $sql_query  = str_replace( '%current_id%', $current_id, $sql_query );
    $results    = $wpdb->get_results( $sql_query );

    if ( empty( $results ) ) {
        return "No results found.";
    }

    $columns = array_map( 'trim', explode( ',', $attributes['columns'] ) );

    $output = [];
    foreach ( $columns as $column ) {
        if ( isset( $results[0]->{$column} ) ) {
            $output[ $column ] = $results[0]->{$column};
        } else {
            $output[ $column ] = 'N/A';
        }
    }

    switch ( strtolower( $attributes['format'] ) ) {
        case 'json':
            return json_encode( $output );
        case 'html':
            $html = "<table><tr>";
            foreach ( $output as $key => $value ) {
                $html .= "<th>" . esc_html( $key ) . "</th>";
            }
            $html .= "</tr><tr>";
            foreach ( $output as $value ) {
                $html .= "<td>" . esc_html( $value ) . "</td>";
            }
            $html .= "</tr></table>";
            return $html;
        case 'text':
        default:
            return implode( ', ', $output );
    }
}
add_shortcode( 'my_jet_query', 'myjeqs_custom_jet_query_shortcode' );

/**
 * A shortcode function to execute a JetEngine Query and return multiple results in various formats.
 *
 * Usage: [my_jet_multi_result_query query_id="123" columns="package_title,package_id" format="json"]
 *
 * @param array $atts User-defined attributes in the shortcode.
 * @return string Output from the executed query in the specified format or an error message.
 */
function myjeqs_jet_multi_result_query_shortcode( $atts ) {
    $attributes = shortcode_atts( [
        'query_id' => 0,
        'columns'  => 'package_title',
        'format'   => 'json',
    ], $atts );

    if ( empty( $attributes['query_id'] ) ) {
        return "Error: query_id is required.";
    }

    $sql_query = myjeqs_fetch_jetengine_query_by_id( $attributes['query_id'] );
    if ( ! $sql_query ) {
        return "Query not found or invalid.";
    }

    global $wpdb;
    $current_id = get_the_ID();
    $sql_query  = str_replace( '%current_id%', $current_id, $sql_query );
    $results    = $wpdb->get_results( $sql_query );

    if ( empty( $results ) ) {
        return "No results found.";
    }

    $columns = array_map( 'trim', explode( ',', $attributes['columns'] ) );

    $output = [];
    foreach ( $results as $result ) {
        $row = [];
        foreach ( $columns as $column ) {
            $row[ $column ] = isset( $result->{$column} ) ? $result->{$column} : 'N/A';
        }
        $output[] = $row;
    }

    switch ( strtolower( $attributes['format'] ) ) {
        case 'json':
            return json_encode( $output );
        case 'html':
            $html = "<table><tr>";
            foreach ( $columns as $column ) {
                $html .= "<th>" . esc_html( $column ) . "</th>";
            }
            $html .= "</tr>";
            foreach ( $output as $row ) {
                $html .= "<tr>";
                foreach ( $row as $cell ) {
                    $html .= "<td>" . esc_html( $cell ) . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
            return $html;
        case 'array':
            ob_start();
            print_r( $output );
            return ob_get_clean();
        case 'text':
        default:
            ob_start();
            foreach ( $output as $row ) {
                echo implode( ', ', $row ) . "\n";
            }
            return ob_get_clean();
    }
}
add_shortcode( 'my_jet_multi_result_query', 'myjeqs_jet_multi_result_query_shortcode' );

/**
 * Add a menu item under the 'Settings' menu in WordPress admin.
 */
function myjeqs_add_admin_menu() {
    add_options_page(
        'My JetEngine Query Shortcodes',
        'JetEngine Shortcodes',
        'manage_options',
        'my-jetengine-query-shortcodes',
        'myjeqs_admin_page'
    );
}
add_action( 'admin_menu', 'myjeqs_add_admin_menu' );

/**
 * Display the admin page content.
 */
function myjeqs_admin_page() {
    ?>
    <div class="wrap">
        <h1>My JetEngine Query Shortcodes</h1>
        <p>
            This plugin provides two shortcodes to execute JetEngine queries and display results in various formats.
        </p>

        <h2>Shortcodes</h2>

        <h3>[my_jet_query]</h3>
        <p>
            Executes a JetEngine query and returns specified columns from a single result.<br>
            <strong>Usage:</strong><br>
            <code>[my_jet_query query_id="123" columns="column1,column2" format="text"]</code>
        </p>
        <ul>
            <li><strong>query_id</strong> (required): The ID of the query saved in JetEngine.</li>
            <li><strong>columns</strong> (optional): Comma-separated list of columns to display. Default is 'package_title'.</li>
            <li><strong>format</strong> (optional): Output format. Options are 'text', 'json', 'html'. Default is 'text'.</li>
        </ul>

        <h3>[my_jet_multi_result_query]</h3>
        <p>
            Executes a JetEngine query and returns multiple results.<br>
            <strong>Usage:</strong><br>
            <code>[my_jet_multi_result_query query_id="123" columns="column1,column2" format="json"]</code>
        </p>
        <ul>
            <li><strong>query_id</strong> (required): The ID of the query saved in JetEngine.</li>
            <li><strong>columns</strong> (optional): Comma-separated list of columns to display. Default is 'package_title'.</li>
            <li><strong>format</strong> (optional): Output format. Options are 'array', 'text', 'json', 'html'. Default is 'json'.</li>
        </ul>

        <h2>Instructions</h2>
        <ol>
            <li>
                <strong>Create a JetEngine Query:</strong> In your WordPress dashboard, navigate to <em>JetEngine &gt; Query Builder</em> and create a new query. Save the query and note down its ID.
            </li>
            <li>
                <strong>Use the Shortcodes:</strong> Insert the shortcodes into your posts or pages where you want to display the query results.
            </li>
            <li>
                <strong>Customize the Output:</strong> Use the 'columns' and 'format' attributes to customize which columns are displayed and in what format.
            </li>
        </ol>

        <h2>Examples</h2>

        <h3>Single Result in Text Format</h3>
        <code>[my_jet_query query_id="123" columns="package_title,package_id" format="text"]</code>

        <h3>Multiple Results in HTML Table</h3>
        <code>[my_jet_multi_result_query query_id="123" columns="package_title,package_id" format="html"]</code>

        <h2>Notes</h2>
        <ul>
            <li><strong>Security:</strong> Ensure your queries are secure and free from vulnerabilities.</li>
            <li><strong>Data Replacement:</strong> The placeholder '%current_id%' in your SQL queries will be replaced with the ID of the current post or page.</li>
            <li><strong>Testing:</strong> Test your queries and shortcodes in a safe environment before deploying to a live site.</li>
        </ul>
    </div>
    <?php
}
