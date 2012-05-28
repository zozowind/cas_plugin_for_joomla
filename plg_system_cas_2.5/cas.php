<?php
/**
 * CAS Authentication Plugin
 *
 * @package		Joomla.Plugin
 * @subpackage	System.cas
 * @since 2.5
 * @author Marco Chen
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

class plgSystemCas extends JPlugin{
    function onAfterInitialise(){        
        // 加载参数获取类文件
        require_once JPATH_LIBRARIES.DS.'joomla'.DS.'html'.DS.'parameter.php';
        // 获取authentication cas 插件及其参数
        $auth_cas = & JPluginHelper::getPlugin('authentication','cas');
        $auth_cas_params = new JParameter($auth_cas->params);
        
        /** CAS config文件 **/
        /** Basic Config of the phpCAS client **/
        // Full Hostname of your CAS Server
        $cas_host = $auth_cas_params->get('host');

        // Context of the CAS Server
        $cas_context = $auth_cas_params->get('context');

        // Port of your CAS server. Normally for a https server it's 443
        $cas_port = intval($auth_cas_params->get('port'));

        // Path to the ca chain that issued the cas server certificate
        $cas_server_ca_cert_path = $auth_cas_params->get('ca_cert_path');
        
        /** End Configuration -- Don't edit below **/

        // Generating the URLS for the local cas example services for proxy testing
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $curbase = 'https://' . $_SERVER['SERVER_NAME'];
        } else {
            $curbase = 'http://' . $_SERVER['SERVER_NAME'];
        }
        if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
            $curbase .= ':' . $_SERVER['SERVER_PORT'];
        }

        $curdir = dirname($_SERVER['REQUEST_URI']) . "/";

        // CAS client nodes for rebroadcasting pgtIou/pgtId and logoutRequest
        $rebroadcast_node_1 = 'http://cas-client-1.example.com';
        $rebroadcast_node_2 = 'http://cas-client-2.example.com';

        // access to a single service
        $serviceUrl = $curbase . $curdir . 'example_service.php';
        // access to a second service
        $serviceUrl2 = $curbase . $curdir . 'example_service_that_proxies.php';

        $pgtBase = preg_quote(preg_replace('/^http:/', 'https:', $curbase . $curdir), '/');
        $pgtUrlRegexp = '/^' . $pgtBase . '.*$/';

        $cas_url = 'https://' . $cas_host;
        if ($cas_port != '443') {
            $cas_url = $cas_url . ':' . $cas_port;
        }
        $cas_url = $cas_url . $cas_context;

        // Set the session-name to be unique to the current script so that the client script
        // doesn't share its session with a proxied script.
        // This is just useful when running the example code, but not normally.
        session_name(
            'session_for:'
            . preg_replace('/[^a-z0-9-]/i', '_', basename($_SERVER['SCRIPT_NAME']))
        );
        // Set an UTF-8 encoding header for internation characters (User attributes)
        header('Content-Type: text/html; charset=utf-8');
        /** config文件结束 **/
        
        /**
         * 检测当前的的用户情况
         * 1. 当前用户已登录，无
         * 2. 没有用户登录
         * 2.1 ticket参数匹配，自动登录
         * 2.2 ticket参数不匹配，无
         */
        // global $mainframe;
        // Load the CAS lib
        require_once dirname(__FILE__).DS.'caslib.php';             
        // Uncomment to enable debugging
        phpCAS::setDebug();
        // Initialize phpCAS
        phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);
        phpCAS::setNoCasServerValidation();
        
        //获取当前用户
        $user = &JFactory::getUser();
        if($user->get('guest')){
            $ticket = JRequest::getVar('ticket');
            //匹配ticket参数
            if(preg_match('/^ST-\d+-[0-9a-zA-Z]{20}-cas$/',$ticket)){
                $app = JFactory::getApplication();
                $credentials = array();
                $options = array();
                if (true === $app->login($credentials, $options)) {
                // Success
                $app->setUserState('users.login.form.data', array());
                $app->redirect(JRoute::_($app->getUserState('users.login.form.return'), false));
                }
            }
            
        }
    }
}
?>