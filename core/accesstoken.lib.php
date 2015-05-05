<?php
namespace LaneWeChat\Core;
/**
 * 微信Access_Token的获取与过期检查
 * Created by Lane.
 * User: lane
 * Date: 13-12-29
 * Time: 下午5:54
 * Mail: lixuan868686@163.com
 * Website: http://www.lanecn.com
 */
class AccessToken{
    /**
     * 获取微信服务器IP列表
     */
    public static function getWeChatIPList(){
        //获取ACCESS_TOKEN
        $accessToken = AccessToken::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token='.$accessToken;
        return Curl::callWebServer($url, '', 'GET');
    }

    /**
     * 获取微信Access_Token
     */
    public static function getAccessToken(){
        //检测本地是否已经拥有access_token，并且检测access_token是否过期
        $accessToken = self::_checkAccessToken();
        if($accessToken === false){
            $accessToken = self::_getAccessToken();
        }
        return $accessToken['access_token'];
    }

    /**
     * @descrpition 从微信服务器获取微信ACCESS_TOKEN
     * @return Ambigous|bool
     */
    private static function _getAccessToken(){
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.WECHAT_APPID.'&secret='.WECHAT_APPSECRET;
        $accessToken = Curl::callWebServer($url, '', 'GET');
        if(!isset($accessToken['access_token'])){
            return Msg::returnErrMsg(MsgConstant::ERROR_GET_ACCESS_TOKEN, '获取ACCESS_TOKEN失败');
        }
        $accessToken['time'] = time();
        $accessTokenJson = json_encode($accessToken);
        Mysql::config(DATABASE_NAME, DATABASE_USER, DATABASE_PASS, DATABASE_HOST, DATABASE_PORT);
        $newAccesstoken = array('accesstoken' => $accessToken['access_token'],'expiresin' => $accessToken['expires_in'],'time' => $accessToken['time']);
        Mysql::insert('wechataccesstoken',$newAccesstoken);
        return $accessToken;
    }

    /**
     * @descrpition 检测微信ACCESS_TOKEN是否过期
     *              -10是预留的网络延迟时间
     * @return bool
     */
    private static function _checkAccessToken(){
        Mysql::config(DATABASE_NAME, DATABASE_USER, DATABASE_PASS, DATABASE_HOST, DATABASE_PORT);
        $result = Mysql::select('wechataccesstoken','','id desc',1);
        if(count($result, 1) > 0){
            $accessToken['access_token'] = $result[0]['accesstoken'];
            $accessToken['expires_in'] = $result[0]['expiresin'];
            $accessToken['time'] = $result[0]['time'];
            if(time() - $accessToken['time'] < $accessToken['expires_in']-10){
                return $accessToken;
            }
        }
        return false;
    }
}
?>
