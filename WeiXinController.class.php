<?php 
namespace Guest\Controller;

use Think\MVC\Controller;

// header("Content-type:text/html;charset=utf-8");

class WeiXinController extends Controller
{
    private $appID      = 'wx4298af218df7b524';
    private $appsecret  = 'bb91b1578327cdcb631ae9fb416d5bbd';

    // 入口验证
    public function index()
    {
        // 获取参数 token timestamp nonce signature echostr
        $token     = 'hello';
        $nonce     = $_GET['nonce'];
        $timestamp = $_GET['timestamp'];
        $signature = $_GET['signature'];        // 1. 这里获取不到值 null
        $echostr   = $_GET['echostr'];

        // 将 token timestamp nonce 组合成数组 然后按 字典序 排序
        $array = array();
        $array = array($nonce,$timestamp,$token);
        sort($array);

        // sha1加密
        $str = sha1( implode( $array ) );

        // 与 signature 进行比较
        if( $str == $signature && $echostr ){
            echo $echostr;
            exit;
        // }else{
        //     $this -> responseMsg();
        }
    }

    // 接受事件推送并回复
    public function responseMsg()
    {
        // 1. 获取到微信推送过来的post数据（ XML 格式）
        $postArr = $GLOBALS['HTTP_RAW_POST_DATA'];

        // 2. 处理消息类型，并设置回复类型和内容
        $postObj = simplexml_load_string( $postArr );
        // 判断该数据包是否是"订阅"的事件推送
        if( strtolower($postObj->MsgType) == 'event'){
            // 如果是关注  subscribe 事件
            if( strtolower($postObj->Event) == 'subscribe' ){

                $content  = '欢迎来到 ☆牍中玉的小木屋☆'."\n".'回复城市名称，可以查看天气哟.';

            }
            else if( strtoupper($postObj->Event) == 'LOCATION' ){
                
                // 获取用户的唯一ID（openid）
                $openid = $postObj->FromUserName;

                // 获取用户的昵称
                $userinfo = $this->getUserInfo( $openid );
                $username = $userinfo['nickname'];

                // 拼接内容
                $content = '尊敬的用户:'.$username."\n".'您当前的位置是：'."\n".'维度:　'.$postObj->Latitude."\n".'经度:　'.$postObj->Longitude;
            }
            else if( strtoupper($postObj->Event) == 'CLICK' ){
                switch($postObj->EventKey){
                    case 'touTiaoXinWen'    : 
                                // 类型,,top(头条，默认),shehui(社会),guonei(国内),guoji(国际),yule(娱乐),tiyu(体育),junshi(军事),keji(科技),caijing(财经),shishang(时尚)
                                $arr = array('top','shehui','guonei','guoji','yule','tiyu','junshi','keji','caijing','shishang');
                                $type = $arr[rand(0,9)];
                                $info = $this -> checkTopNews($type);
                                $data = $info['result']['data'];

                                $index = array_rand($data,5);  

                                foreach($index as $k => $v){
                                    $datas[$k] = $data[$index[$k]];
                                }             

                                $content = $info['result']['data'][3]['title'];
                                $num     = count($datas);
                                break;
                    case 'moBanXinXi'      :
                                $content = '查看模板消息';
                                $this    -> sendTemplateMsg();
                                break;
                    case 'fenXiang'        :
                                $content = '分享信息';
                                $this    -> shareWx();
                                break;
                }
            }

            // 回复用户消息（纯文本格式）
            $toUser   = $postObj->FromUserName;
            $fromUser = $postObj->ToUserName;
            $time     = time();

            // 判断
            if( strtoupper($postObj->Event) == 'CLICK' && $postObj->EventKey == 'touTiaoXinWen' ){
                $msgType      = 'news';
                $ArticleCount = $num;

                $template    .= "
                                <xml>
                                <ToUserName><![CDATA[%s]]></ToUserName>
                                <FromUserName><![CDATA[%s]]></FromUserName>
                                <CreateTime>%s</CreateTime>
                                <MsgType><![CDATA[%s]]></MsgType>
                                <ArticleCount>%s</ArticleCount>
                                <Articles>";

                foreach($datas as $k => $v){

                    $template    .= "  
                                    <item>
                                    <Title><![CDATA[".$v['title']."]]></Title>
                                    <Description><![CDATA[".$v['title']."]]></Description>
                                    <PicUrl><![CDATA[".$v['thumbnail_pic_s03']."]]></PicUrl>
                                    <Url><![CDATA[".$v['url']."]]></Url>
                                    </item>";
                }

                $template .=    "</Articles>
                                </xml>";
                $info     = sprintf($template,$toUser,$fromUser,$time,$msgType,$ArticleCount);

            }
            else{
                $msgType  = 'text';
                $template = "
                            <xml>
                            <ToUserName><![CDATA[%s]]></ToUserName>
                            <FromUserName><![CDATA[%s]]></FromUserName>
                            <CreateTime>%s</CreateTime>
                            <MsgType><![CDATA[%s]]></MsgType>
                            <Content><![CDATA[%s]]></Content>
                            </xml>";
                $info     = sprintf($template,$toUser,$fromUser,$time,$msgType,$content);
            }

            echo $info;
        }

        // 判断该数据包是否是普通文本信息
        if( strtolower($postObj->MsgType) == 'text' ){

            if( trim($postObj->Content) == 'xiaozhe' ){
                $content = 'Red Apple';
            }

            // 聚合数据接口
            else{
                // 1. 获取要查询的城市名称
                $city = $postObj->Content;

                // 2. 配置appkey
                $appkey = 'e8f1f4599ff9e5a2edb612c432effb6c';
                
                // 3. 设置URL
                $url = 'http://op.juhe.cn/onebox/weather/query?cityname='.urlencode($city).'&key='.$appkey;

                // 4. 执行
                $res = $this -> httpGet($url);
                
                $info = json_decode($res,true);

                $content = $info['result']['data']['realtime']['city_name']."\n".$info['result']['data']['realtime']['date']."\n星期 ".$info['result']['data']['realtime']['week']."\n".$info['result']['data']['realtime']['moon']."\n气温: ".$info['result']['data']['realtime']['weather']['temperature']."\n湿度: ".$info['result']['data']['realtime']['weather']['humidity']."\n\n风向: ".$info['result']['data']['realtime']['wind']['direct']."\n风力: ".$info['result']['data']['realtime']['wind']['power']."\n\n穿衣指数: ".$info['result']['data']['life']['info']['chuanyi'][0]."\n".$info['result']['data']['life']['info']['chuanyi'][1]."\n\n感冒指数: ".$info['result']['data']['life']['info']['ganmao'][0]."\n".$info['result']['data']['life']['info']['ganmao'][1]."\n\n污染指数: ".$info['result']['data']['life']['info']['wuran'][0]."\n".$info['result']['data']['life']['info']['wuran'][1]."\n\n运动指数: ".$info['result']['data']['life']['info']['yundong'][0]."\n".$info['result']['data']['life']['info']['yundong'][1]."\n\n紫外线强度: ".$info['result']['data']['life']['info']['ziwaixian'][0]."\n".$info['result']['data']['life']['info']['ziwaixian'][1];
            }


            // 一下是百度 api 接口 （ 有时候不能用 ）
            // else{
            //     $info = $postObj->Content;

            //     $ch = curl_init();
            //     $url = 'http://apis.baidu.com/apistore/weatherservice/cityname?cityname='.$info;
            //     $header = array(
            //         'apikey: b3aca9b5f8a0432cae3002caacdc7ea9',
            //     );
            //     // 添加apikey到header
            //     curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
            //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            //     // 执行HTTP请求
            //     curl_setopt($ch , CURLOPT_URL , $url);
            //     $res = curl_exec($ch);

            //     $arr = json_decode($res,true);

            //     $content = $arr['retData']['city']."\n".$arr['retData']['date']."\n".$arr['retData']['time']."\n".$arr['retData']['weather']."\n".$arr['retData']['temp']."\n".$arr['retData']['l_tmp']."\n".$arr['retData']['h_tmp']."\n".$arr['retData']['WD']."\n".$arr['retData']['WS']."\n"; 
            // }

            // 回复用户消息（纯文本格式）
            $toUser   = $postObj->FromUserName;
            $fromUser = $postObj->ToUserName;
            $time     = time();
            $msgType  = 'text';
            $template = "
                        <xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        </xml>";
            $info     = sprintf($template,$toUser,$fromUser,$time,$msgType,$content);
            echo $info;
        }
    }

    // 获取用户信息
    public function getUserInfo( $openid = 'o8DUkwNfbLxYkYYwKEholaxou-DM' )
    {
        $access_token = $this -> checkAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN ';
        $info = $this -> httpGet($url);

        $data = json_decode($info,true);
        return $data;
    }

    // 自定义模板消息
    public function sendTemplateMsg(){
        // 1. 获取到access_token
        $access_token = $this -> checkAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
        // 2. 组装数组

        $array = array(
             'touser'=>'o8DUkwNfbLxYkYYwKEholaxou-DM',
             'template_id'=>'CY1Tu_Ymh41mofkzY2BFDrb97W2ZccFwIASdoEyljTU',
             'url'=>'http://wx.qlogo.cn/mmopen/5GX14euMveOGazNOlb4K7u7bZKv3skWGIucKrMUibFUqpxHh6ibq4csUgObXBdBb43JGMSBtACicryD6vfDmgWtOdYDnsHDZfBg/0',
             'data'=>array(
                'name' => array( 'value'=>'Red Apple','color'=>"#ff0000" ),
                'money'=> array( 'value'=>'请尽情使用该订阅号','color'=>"#173177" ),
                'date' => array( 'value'=>date('Y-m-d H:i:s',time()),'color'=>"#173177" ),
                ),
            );

        // 3. 将数组 -> json
        $data = json_encode($array);

        // 4. 调用curl函数
        $res = $this -> httpPost($url,$data);
        var_dump($res);
    }

    // 获取用户的openID
    // public function getCode()
    // {
    //     $appid = 'wx4298af218df7b524';
    //     $redirect_uri = urlencode('http://wei.test.meiniucn.com/Guest/WeiXin/getUserDetail');
    //     // 1. 获取到code
    //     $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_userinfo&state=xiaozhe#wechat_redirect';
    //     // 跳转页面
    //     header('location:'.$url);
    // }

    // public function getUserDetail()
    // {
    //     $appid     = 'wx4298af218df7b524';
    //     $appsecret = 'bb91b1578327cdcb631ae9fb416d5bbd';
    //     $code      = $_GET['code'];

    //     // 2. 获取到网页授权的access_token的值
    //     $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appid.'&secret='.$appsecret.'&code='.$code.'&grant_type=authorization_code';
    //     $res           = $this -> httpGet($url);
    //     $access_token  = $res['access_token'];
    //     $refresh_token = $res['refresh_token'];
        
    //     if( file_exists('wy_access_token.txt') ){
    //         // 读取缓存文件
    //         $info          = file_get_contents('wy_access_token.txt');
    //         $data          = explode('|',$info);
    //         $expires_in    = $info[1];
    //     }

    //     // 检测 access_token 是否已经失效
    //     if( time() > $expires_in ){
    //         $url  = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.$appid.'&grant_type=refresh_token&refresh_token='.$refresh_token;
    //         $res  = $this -> httpGet($url);
    //         $res['expires_in'] = time() + 7000;
    //         $info = $res['access_token'].'|'.$res['expires_in'];

    //         // 缓存 $expires_in
    //         file_put_contents('wy_access_token.txt',$info);          
    //     }

    //     $openid  = $res['openid'];

    //     // 3. 拉取用户的openssid
    //     $url     = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
    //     $res     = $this -> httpGet($url);

    //     var_dump($res);
    // }

    // 自定义菜单
    public function menu()
    {
        $access_token = $this -> getAccessToken();
        
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;

        $str = '{
                    "button":[
                    {
                      "name":"作者",
                      "sub_button":[
                      {
                        "type":"click",
                        "name":"牍中玉",
                        "key":"userName"
                      }]
                    },
                    {
                       "name":"电影娱乐",
                       "sub_button":[
                       {    
                           "type":"view",
                           "name":"新浪新闻",
                           "url":"http://news.sina.com.cn/"
                        },
                        {
                           "type":"view",
                           "name":"优酷视频",
                           "url":"http://www.youku.com/"
                        },
                        {
                           "type":"view",
                           "name":"酷我音乐",
                           "url":"http://www.kuwo.cn/"
                        }]
                    },
                    {  
                      "name":"便捷生活",
                      "sub_button":[
                      {
                        "type":"view",
                        "name":"分享",
                        "url":"http://wei.test.meiniucn.com/Guest/WeiXin/shareWx"
                      },
                      {
                        "type": "click", 
                        "name": "头条新闻", 
                        "key": "touTiaoXinWen" 
                      },                      
                      {
                        "type": "scancode_push", 
                        "name": "扫码推事件", 
                        "key": "saoMaTui"
                      },                     
                      {
                        "type":"click",
                        "name":"模板信息",
                        "key":"moBanXinXi"
                      },
                      {
                        "type":"location_select",
                        "name":"位置信息",
                        "key":"weiZhiXinXi"                        
                      }]
                    }]
                }'; 

        $res = $this -> httpPost($url,$str);

        echo $res;
    }


/******************************************************************/
/**
 * 测试的时候用的，删除缓存文件或者查看缓存文件的内容
 */
    public function deleteDir()
    {
        // @unlink('basic_access_token.txt');
        @unlink('jsapi_ticket.txt');
        // $info = file_get_contents('basic_access_token.txt');
        // var_dump($info);
    }
/******************************************************************/


    // 检测access_token是否超时
    public function checkAccessToken()
    {
       if(file_exists('basic_access_token.txt')){
            $msg          = file_get_contents('basic_access_token.txt');
            $info         = explode('|',$msg);
            $expires_in   = $info[1];

            // 判断access_token的值是否有效
            if( time() < intVal( $expires_in ) ){
                $access_token = $info[0];
            }else{
                // access_token的值失效，重新生成
                $access_token = $this -> getAccessToken();
            }
        }else{
            // 第一次访问时，没有缓存文件，生成文件
            $access_token = $this -> getAccessToken();
        }

        return $access_token;
    }

    // 获取access_token的值
    private function getAccessToken()
    {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appID."&secret=".$this->appsecret;

        // $res = file_get_contents($url);
        $res = $this->httpGet($url);

        $data = json_decode($res,true);

        $access_token = $data['access_token'];
        $expires_in   = time()+7000;

        $info = $access_token.'|'.$expires_in;

        file_put_contents('basic_access_token.txt',$info);

        return $access_token;
    }

    // 头条新闻
    private function checkTopNews( $type = 'top' )
    {
        $appkey = 'fbbb4bdceb32caa19ee8def046c8f5c9';
        $url = 'http://v.juhe.cn/toutiao/index?type='.$type.'&key='.$appkey;
        $res = $this -> httpGet($url);

        $info = json_decode($res,true);
        return $info;
    }

    /**
     * 获取全局票据 jsapi_ticket
     */
    private function getJsApiTicket()
    {
        // 检测缓存文件是否存在
        if(file_exists('jsapi_ticket.txt')){
            $data1 = file_get_contents('jsapi_ticket.txt');
            $info1 = explode('|', $data1);
            $jsapi_ticket = $info1[0];
            $expires_in = $info1[1];
        }

        // 检测jsapi_ticket是否已经过期
        if( isset($expires_in) && ( $expires_in > time() ) ){
            $jsapi_ticket = $info[0];
        }else{
            $access_token = $this -> checkAccessToken();
            $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
            $res = $this -> httpGet($url);

            $res = json_decode($res,true);
            $jsapi_ticket = $res['ticket'];

            $data['jsapi_ticket'] = $res['ticket'];
            $data['expires_in']   = time() + 7000;

            // 将数据存入缓存文件
            $info = implode('|',$data);
            file_put_contents('jsapi_ticket.txt', $data);
        }

        return $jsapi_ticket;
    }

    private function getRandCode( $num = 16 )
    {
        $array = array(
            'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','a','b','c','d','e','f','g','h','i','g','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','0','1','2','3','4','5','6','7','8','9'
        );

        $tmpStr = '';
        $max    = count($array);

        for($i=1;$i<=$num;$i++){
            $tmpStr .= $array[rand(0,$max-1)];
        }

        return $tmpStr;
    }

    /**
     * js-sdk
     */
    public function shareWx()
    {
        // 1. 获取jsapi_ticket票据
        $jsapi_ticket = $this -> getJsApiTicket();
        $timestamp    = time();
        $noncestr     = $this -> getRandCode();
        $url          = "http://wei.test.meiniucn.com/Guest/WeiXin/shareWx";

        // 2. 获取签名 $signature
        $signature = "jsapi_ticket=".$jsapi_ticket."&noncestr=".$noncestr."&timestamp=".$timestamp."&url=".$url;
        $signature = sha1( $signature );

        $this -> assign('timestamp',$timestamp);
        $this -> assign('signature',$signature);
        $this -> assign('noncestr',$noncestr);

        $this -> display();
    }

    public function myLocation($latitude,$longitude)
    {
        // 1. 获取jsapi_ticket票据
        $jsapi_ticket = $this -> getJsApiTicket();
        $timestamp    = time();
        $noncestr     = $this -> getRandCode();
        $url          = "http://wei.test.meiniucn.com/Guest/WeiXin/myLocation";

        // 2. 获取签名 $signature
        $signature = "jsapi_ticket=".$jsapi_ticket."&noncestr=".$noncestr."&timestamp=".$timestamp."&url=".$url;
        $signature = sha1( $signature );

        $this -> assign('timestamp',$timestamp);
        $this -> assign('signature',$signature);
        $this -> assign('noncestr',$noncestr);

        $this -> assign('latitude',$latitude);
        $this -> assign('longitude',$longitude);

        $this -> display();
    }

    /**
     * 在脚本中向某一个url地址发送get请求
     */
    private function httpGet($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // 一下两句不知道为什么，必须添加，才能保证 curl 正常使用

        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
        // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);        
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        $res = curl_exec($ch);

        curl_close($ch);

        if( curl_errno( $ch )){
            var_dump( curl_error($ch) );
        }

        return $res;
    }

    /**
     * 在脚本中向某一个URL地址发送post请求
     */
    private function httpPost($url, $data = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $res = curl_exec($ch);
        curl_close($ch);

        if( curl_errno( $ch )){
            var_dump( curl_error($ch) );
        }

        return $res;
    }

}