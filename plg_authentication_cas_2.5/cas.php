<?php

// No direct access
defined('_JEXEC') or die;

/**
 * CAS Authentication Plugin
 *
 * @package		Joomla.Plugin
 * @subpackage	Authentication.cas
 * @since 2.5
 * @author Marco Chen
 */

class plgAuthenticationCas extends JPlugin
{
	/**
	 * This method should handle any authentication and report back to the subject
	 *
	 * @access	public
	 * @param   array	$credentials Array holding the user credentials
	 * @param	array   $options	Array of extra options
	 * @param	object	$response	Authentication response object
	 * @return	object	boolean
	 * @since 1.5
	 */
    
	function onUserAuthenticate($credentials, $options, &$response)
	{
		// Initialise variables.
		$userdetails = null;
		$success = 0;
		$userdetails = array();
        
		// For JLog
		$response->type = 'CAS';
        // Full Hostname of your CAS Server
        $cas_host = $this->params->get('host');
        // Context of the CAS Server
        $cas_context = $this->params->get('context');
        // Port of your CAS server. Normally for a https server it's 8443        
        $cas_port = intval($this->params->get('port'));
        // 获取票据值
        $ticket = JRequest::getVar('ticket');
        // 构建验证url
        $str = preg_split('/\?/',$_SERVER['REQUEST_URI']);
        $service = 'http://'.$_SERVER['HTTP_HOST'].$str[0];
        $cas_url = 'https://'.$cas_host.':'.strval($cas_port).$cas_context;       
        $url = $cas_url.'/serviceValidate?service='.$service.'&ticket='.$ticket;
        // 获取验证结果并构建Dom树
        $string = file_get_contents($url);
        $result = new DOMDocument;
        $result->loadXML($string);        
        if($ticket==""){
            phpCAS::forceAuthentication();    
        }else{
            $rf = $result->getElementsByTagName('authenticationFailure');
            if($rf->length!=0){
                $response->status		 = JAuthentication::STATUS_FAILURE; 
                $response->error_message = $rf->item(0)->nodeValue;
            }else{
                $cas_user = $result->getElementsByTagName('user');
                $username = $cas_user->item(0)->nodeValue;
                // 检测该用户是否在joomla系统是否存在
                $db = JFactory::getDBO();
                $query = "SELECT count(*) FROM #__users WHERE username = '".$username."'";  
                $db->setQuery($query);
                $db->loadResult();
                if($db->loadResult()==0){
                    //判断是否自动添加用户
                    if($this->params->get('autoregister', 0)==1){
                    //启动自动添加用户前需要确认CAS是否已配置支持额外属性输出                      
                        $newuser = new JUser();
                        $newuser->id = 0;
                        $newuser->username = $username;
                        $newuser->name = $result->getElementsByTagName('name')->item(0)->nodeValue;
                        $newuser->email = $result->getElementsByTagName('email')->item(0)->nodeValue;
                        $newuser->password = JUserHelper::getCryptedPassword($email);
                        $newuser->sendEmail = 0;
                        $newuser->block = 0;
                        $newuser->registerDate = date("Y-m-d H:i:s");
                        if($newuser->save()){
                            $ugquery = "INSERT INTO `#__user_usergroup_map` (`user_id`, `group_id`) VALUES ((SELECT id FROM #__users WHERE username = '".$username."'), (SELECT id FROM #__usergroups WHERE title = 'Registered'))";
                            $db->setQuery($ugquery);
                            $db->query();
                            $response->username= $username;
                            $response->status		= JAuthentication::STATUS_SUCCESS;
                            $response->error_message = '';                           
                        }else{
                            $response->status		= JAuthentication::STATUS_FAILURE; 
                            $response->error_message = JText::_('JGLOBAL_AUTH_AUTO_ADD_USER_FAILURE');
                        }
                    }else{
                       $response->status		= JAuthentication::STATUS_FAILURE; 
                       $response->error_message = JText::_('JGLOBAL_AUTH_NOT_EXIST_AND_CONTACT_ADMINISTRATOR');
                    }
                }else{
                    $response->username= $cas_user->item(0)->nodeValue;
                    $response->status		= JAuthentication::STATUS_SUCCESS;
                    $response->error_message = '';
                }
            }        
        }
    }
}
?>