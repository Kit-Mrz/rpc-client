<?php
/**
+----------------------------------------------------------------------
| swoolefy framework bases on swoole extension development, we can use it easily!
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
+----------------------------------------------------------------------
 */

namespace Rpc\Client;

abstract class AbstractSocket {

    /**
     * json
     */
    const DECODE_JSON = 1;

    /**
     * php serialize
     */
    const DECODE_PHP = 2;

    /**
     * 定义序列化的方式
     */
    const SERIALIZE_TYPE = [
        'json' => 1,
        'serialize' => 2,
    ];

    /**
     * $client client对象
     * @var [type]
     */
    public $client;

    /**
     * $clientService 客户端的对应服务名
     * @var [type]
     */
    protected $clientServiceName;

    /**
     * $header_struct 服务端打包包头结构,key代表的是包头的字段，value代表的pack的类型
     * @var array
     */
    protected $server_header_struct = ['length'=>'N'];

    /**
     * $client_header_struct 客户端析包结构,key代表的是包头的字段，value代表的pack的类型
     * @var array
     */
    protected $client_header_struct = [];

    /**
     * $client_pack_setting client的分包数据协议设置
     * @var array
     */
    protected $client_pack_setting = [];

    /**
     * $pack_length_key 包的长度设定的key，与$header_struct里设置的长度的key一致
     * @var string
     */
    protected $pack_length_key = 'length';

    /**
     * $client_serialize_type 客户端设置数据序列化的方式
     * @var string
     */
    protected $client_serialize_type = 'json';

    /**
     * $server_serialize_type 服务器端设置数据序列化的方式
     * @var string
     */
    protected $server_serialize_type = 'json';

    /**
     * 每次请求调用的串号id
     */
    protected $request_id = null;

    /**
     * $request_header 每次请求服务的包头信息
     * @var array
     */
    protected $request_header = [];

    /**
     * $pack_eof eof分包时设置
     * @var string
     */
    protected $pack_eof = "\r\n\r\n";

    /**
     * $remote_servers 请求的远程服务ip和端口
     * @var array
     */
    protected $remote_servers = [];

    /**
     * $timeout 连接超时时间，单位s
     * @var float
     */
    protected $timeout = 0.5;

    protected $haveSwoole = false;
    protected $haveSockets = false;

    /**
     * $is_pack_length_type client是否使用length的check来检查分包
     * @var boolean
     */
    protected $is_pack_length_type = true;

    /**
     * $is_swoole_env 是在swoole环境中使用，或者在apache|php-fpm中使用
     * @var boolean
     */
    protected $is_swoole_env = false;

    /**
     * $swoole_keep
     * @var boolean
     */
    protected $swoole_keep = true;

    /**
     * @var bool
     */
    protected $persistent = false;

    /**
     * $ERROR_CODE
     */
    protected $status_code;

    /**
     * $response_pack_data 响应的完整数据包，包括包头和包头，waitRecv()，multiRecv()调用后，可通过getResponsePackData函数获取
     * @var array
     */
    protected $response_pack_data = [];

    /**
     * $recvWay
     * @var boolean
     */
    protected $recvWay = RpcClientConst::WAIT_RECV;

    /**
     * 开始rpc请求的时间，单位us
     * @var null
     */
    protected $start_rpc_time = null;
    /**
     * rpc调用返回获取到结果的时间，单位us
     * @var null
     */
    protected $end_rpc_time = null;
    /**
     * 是否启用rpc请求的时间计算，默认false
     * @var bool
     */
    protected $is_end_rpc_time = false;

    /**
     * @var array
     */
    protected $client_body_buff = [];

    /**
     * $client_id 每个client_service的唯一id
     * @var null
     */
    protected $client_id = null;

    /**
     * $group_multi_id 并行调用分组id
     * @var null
     */
    protected $group_multi_id = null;

    /**
     * @var array
     */
    protected $args = [];

    /**
     * @var null
     */
    protected $persistent_client_name = null;

    /**
     * __construct 初始化
     * @param array $setting
     */
    public function __construct(
        array $setting = [],
        array $server_header_struct = [],
        array $client_header_struct = [],
        string $pack_length_key = 'length'
    ) {
        $this->client_pack_setting = array_merge($this->client_pack_setting, $setting);
        $this->server_header_struct = array_merge($this->server_header_struct, $server_header_struct);
        $this->client_header_struct = $client_header_struct;
        $this->pack_length_key = $pack_length_key;
        $this->haveSwoole = extension_loaded('swoole');
        $this->haveSockets = extension_loaded('sockets');

        if(isset($this->client_pack_setting['open_length_check']) && isset($this->client_pack_setting['package_length_type'])) {
            $this->is_pack_length_type = true;
        }else {
            // 使用eof方式分包
            $this->is_pack_length_type = false;
            if(isset($this->client_pack_setting['package_eof'])) {
                $this->pack_eof = $this->client_pack_setting['package_eof'];
            }
        }
    }

    /**
     * addServer 添加服务器
     * @param mixed  $servers
     * @param float   $timeout
     * @param integer $noblock
     */
    public function addServer($servers, $timeout = 0.5, $noblock = 0) {
        if(!is_array($servers)) {
            if(strpos($servers, ':')) {
                list($host, $port) = explode(':', $servers);
                $servers = [$host, $port];
            }
        }
        $this->remote_servers[] = $servers;
        $this->timeout = $timeout;
    }

    /**
     * setPackHeaderStruct   设置包头结构体
     * @param    array    $header_struct
     */
    public function setPackHeaderStruct(array $header_struct = []) {
        $this->server_header_struct = array_merge($this->server_header_struct, $header_struct);
        return $this->server_header_struct;
    }

    /**
     * getPackHeaderStruct  获取包头结构体
     * @return   array
     */
    public function getPackHeaderStruct() {
        return $this->server_header_struct;
    }

    /**
     * setClientPackSetting 设置client实例的pack的长度检查
     * @param   array  $client_pack_setting
     */
    public function setClientPackSetting(array $client_pack_setting = []) {
        return $this->client_pack_setting = array_merge($this->client_pack_setting, $client_pack_setting);
    }

    /**
     * getClientPackSetting 获取client实例的pack的长度检查配置
     * @param   array  $client_pack_setting
     */
    public function getClientPackSetting() {
        return $this->client_pack_setting;
    }

    /**
     * setClientServiceName 设置当前的客户端实例的对应服务名
     * @param   string  $clientServiceName
     */
    public function setClientServiceName(string $clientServiceName) {
        return $this->clientServiceName = $clientServiceName;
    }

    /**
     * getClientServiceName
     * @return  string
     */
    public function getClientServiceName() {
        return $this->clientServiceName;
    }

    /**
     * setPackLengthKey 设置包头控制包体长度的key,默认length
     * @param   string   $pack_length_key
     */
    public function setPackLengthKey(string $pack_length_key = 'length') {
        $this->pack_length_key = $pack_length_key;
        return true;
    }

    /**
     * getPackLengthKey 设置包头控制包体长度的key,默认length
     */
    public function getPackLengthKey() {
        return $this->pack_length_key;
    }

    /**
     * setClientSerializeType 设置client端数据的序列化类型
     * @param    string   $client_serialize_type
     */
    public function setClientSerializeType($client_serialize_type) {
        if($client_serialize_type) {
            $this->client_serialize_type = $client_serialize_type;
        }
    }

    /**
     * getServerSerializeType  获取服务端实例的序列化类型
     * @return  string
     */
    public function getServerSerializeType() {
        return $this->server_serialize_type;
    }

    /**
     * setServerSerializeType 设置服务端数据的序列化类型
     * @param    string   $client_serialize_type
     */
    public function setServerSerializeType($server_serialize_type) {
        if($server_serialize_type) {
            $this->server_serialize_type = $server_serialize_type;
        }
    }

    /**
     * getClientSerializeType  获取客户端实例的序列化类型
     * @return  string
     */
    public function getClientSerializeType() {
        return $this->client_serialize_type;
    }

    /**
     * isPackLengthCheck  client是否使用length的检查
     * @return   boolean
     */
    public function isPackLengthCheck() {
        return $this->is_pack_length_type;
    }

    /**
     * setIsSwooleEnv
     * @param    bool|boolean  $is_swoole_env
     */
    public function setSwooleEnv(bool $is_swoole_env = false) {
        $this->is_swoole_env = $is_swoole_env;
        return true;
    }

    /**
     * getIsSwooleEnv
     * @param    bool|boolean  $is_swoole_env
     */
    public function isSwooleEnv() {
        return $this->is_swoole_env;
    }

    /**
     * setSwooleKeep
     * @param    bool|boolean  $is_swoole_keep
     */
    public function setSwooleKeep(bool $is_swoole_keep = true) {
        $this->swoole_keep = $is_swoole_keep;
        return true;
    }

    /**
     * isSwooleKeep
     * @return   boolean
     */
    public function isSwooleKeep() {
        return $this->swoole_keep;
    }

    /**
     * @return mixed
     */
    public function isPersistent() {
        return $this->persistent;
    }

    /**
     * @param bool $persistent
     */
    public function setPersistent(bool $persistent = false) {
        $this->persistent = $persistent;
    }

    /**
     * disConnect()
     */
    public function disConnect() {
        unset($this->client);
    }

    /**
     * 设置请求的开始时间，单位us
     */
    public function setStartRpcTime() {
        $this->start_rpc_time = microtime(true);
    }
    /**
     * 获取请求的开始时间，单位us
     * @return null
     */
    public function getStartRpcTime() {
        return $this->start_rpc_time;
    }
    /**
     * 设置rpc请求结束的时间，单位us
     */
    public function setEndRpcTime() {
        $this->end_rpc_time = microtime(true);
    }
    /**
     * 获取rpc请求结束的时间，单位us
     * @return null
     */
    public function getEndRpcTime() {
        return $this->end_rpc_time;
    }
    /**
     * 启用rpc的请求时间记录
     */
    public function enableRpcRequestTime() {
        $this->is_end_rpc_time = true;
        return $this;
    }
    /**
     * 判断是否启用rpc时间记录请求
     * @return bool
     */
    public function isEnableRpcTime() {
        return $this->is_end_rpc_time;
    }
    /**
     * 获取rpc请求的总时间，单位毫秒ms
     */
    public function getRpcRequestTime() {
        if(isset($this->start_rpc_time) && isset($this->end_rpc_time)) {
            return number_format(($this->end_rpc_time - $this->start_rpc_time) * 1000, 3);
        }
        return null;
    }

    /**
     * setErrorCode  设置实例对象状态码
     * @param int $coode
     */
    public function setStatusCode($code) {
        $this->status_code = $code;
        return true;
    }

    /**
     * getStatusCode 获取实例对象状态码
     * @return  int
     */
    public function getStatusCode() {
        return $this->status_code;
    }

    /**
     * heartbeat 客户端定时心跳检测
     * @param    int   $time
     * @param    array     $header
     * @param    \Closure  $callable
     * @return   void
     */
    public function heartbeat(int $time = 10 * 1000, array $header = [], $callable = null) {
        // 心跳，则该client_service强制长连接
        $this->setPersistent(true);
        $this->args['persistent'] = true;
        if($this->isSwooleEnv() && ($this->isSwooleKeep() || $this->isPersistent())) {
            swoole_timer_tick($time, function($timer_id, $header) use ($callable) {
                try{
                    $this->waitCall('Swoolefy\\Core\\BService::ping', 'ping', $header);
                }catch (\Throwable $e) {
                    $this->disConnect();
                    $this->reConnect();
                    $this->waitCall('Swoolefy\\Core\\BService::ping', 'ping', $header);
                }
                list($header, $data) = $this->waitRecv(3);
                if($data && $callable instanceof \Closure) {
                    //return call_user_func_array($callable->bindTo($this, __CLASS__), [$data, $timer_id]);
                    return $callable->call($this, $data, $timer_id);
                }
                return null;
            }, $header);
        }
    }

    /**
     * connect 连接
     * @param  string  $host
     * @param  string  $port
     * @param  float   $tomeout
     * @param  integer $noblock
     * @return mixed
     */
    abstract public function connect($host = null, $port = null , $timeout = 0.5, $noblock = 0);

    /**
     * reConnect  最多尝试重连次数，默认尝试重连1次
     * @param   int  $times
     * @return  void
     */
    abstract public function reConnect(int $times = 1);

    /**
     * getSocketClient 获取当前的socket_client实例
     * @return   mixed
     */
    public function getSocketClient() {
        if($this->client) {
            return $this->client;
        }
        return false;
    }

    /**
     * setSwooleClient
     * @param object $swoole_client
     */
    public function setSocketClient($sock_client) {
        $this->client = $sock_client;
        return true;
    }

    /**
     * setClientId
     * @param string $client_id
     */
    public function setClientId(string $client_id) {
        $this->client_id = $client_id;
        return true;
    }

    /**
     * getClientId
     * @return string
     */
    public function getClientId() {
        return $this->client_id;
    }

    /**
     * setGroupMultiId 并行调用所属分组id
     * @param string|null $client_multi_id [description]
     */
    public function setGroupMultiId(string $group_multi_id = null) {
        $this->group_multi_id = $group_multi_id;
        return true;
    }

    /**
     * getGroupMultiId
     * @return string
     */
    public function getGroupMultiId() {
        return $this->group_multi_id;
    }

    /**
     * @param array $args
     */
    public function setArgs($args = []) {
        $this->args = $args;
    }

    /**
     * getArgs
     */
    public function getArgs() {
        return $this->args;
    }

    /**
     * getRequestId  获取当前的请求的串号id
     * @return   string
     */
    public function getRequestId() {
        return $this->request_id;
    }

    /**
     * 设置接收数据方式
     */
    public function setRecvWay($recv_way = null) {
        if(in_array($recv_way, [RpcClientConst::MULTI_RECV, RpcClientConst::WAIT_RECV])) {
            $this->recvWay = $recv_way;
        }
    }

    /**
     * @param string|null $persistent_client_name
     */
    public function setPersistentName(string $persistent_client_name = null) {
        $this->persistent_client_name = $persistent_client_name;
    }

    /**
     * @return null
     */
    public function getPersistentName() {
        return $this->persistent_client_name;
    }
    /**
     * isWultiRecv 判断是否是并行接收数据
     * @return boolean
     */
    public function isMultiRecv() {
        if($this->recvWay == RpcClientConst::MULTI_RECV) {
            return true;
        }
        return false;
    }

    /**
     * isWaitRecv 是否是阻塞方式
     * @return boolean
     */
    public function isWaitRecv() {
        if($this->recvWay == RpcClientConst::WAIT_RECV) {
            return true;
        }
        return false;
    }

    /**
     * getecvWay 获取接收数据方式
     * @return
     */
    public function getecvWay() {
        return $this->recvWay;
    }

    /**
     * buildHeaderRequestId 发起每次请求建立一个请求串号request_id
     * @param  array    $header_data
     * @param  string   $request_id_key  默认request_id
     * @param  int      $length          默认26字符(26字节)
     * @return $this
     */
    public function buildHeaderRequestId(array $header_data, string $request_id_key = 'request_id', int $length = 26) {
        $this->request_header = RpcClientManager::getInstance()->buildHeaderRequestId($header_data, $request_id_key, $length);
        return $this;
    }

    /**
     * parseData 分析数据
     * @param  string $callable
     * @return array
     */
    public function parseCallable(& $callable) {
        if(is_string($callable)) {
            $class_action = explode('::', $callable);
            if(count($class_action) == 2) {
                $callable = $class_action;
            }else {
                $this->setStatusCode(RpcClientConst::ERROR_CODE_CALLABLE);
            }
        }
    }

    /**
     * send 数据发送
     * @param   string   $callable
     * @param   mixed    $params数据序列化模式
     * @param   array    $header  数据包头数据，如果要传入该参数，则必须是由buildHeaderRequestId()函数产生返回的数据
     * @return  boolean
     */
    abstract public function waitCall($callable, $params, array $header = []);

    /**
     * recv 阻塞等待接收数据
     * @param    float $timeout
     * @param    int  $size
     * @param    int  $flags
     * @return   array
     */
    abstract public function waitRecv(float $timeout = 5, int $size = 2048, int $flags = 0);

    /**
     * getResponsePackData 获取服务返回的整包数据
     * @param   string  $serviceName
     * @return  array
     */
    public function getResponsePackData() {
        $request_id = $this->getRequestId();
        if($this->isMultiRecv()) {
            // mutilRecv 并行调用获取数据
            $response_pack_data = RpcClientManager::getInstance()->getAllResponsePackData($this->getGroupMultiId());
        }else {
            // waitRecv 阻塞调用时获取数据
            $response_pack_data = $this->response_pack_data;
        }
        $pack_data = $response_pack_data[$request_id] ?: [];
        return $pack_data;
    }

    /**
     * getResponseBody 获取服务响应的包体数据
     * @return  array
     */
    public function getResponsePackBody() {
        list($header, $body) = $this->getResponsePackData();
        return $body ?: [];
    }

    /**
     * getResponseBody 获取服务响应的包头数据
     * @return  array
     */
    public function getResponsePackHeader() {
        list($header, $body) = $this->getResponsePackData();
        return $header ?: [];
    }

    /**
     * isCallSuccess waitCall发送数据是否成功
     * @return boolean
     */
    public function isCallSuccess() {
        if(in_array($this->getStatusCode(), [RpcClientConst::ERROR_CODE_SEND_SUCCESS, RpcClientConst::ERROR_CODE_SECOND_SEND_SUCCESS])) {
            return true;
        }
        return false;
    }

    /**
     * enpack
     * @param  array  $data
     * @param  array  $header
     * @param  mixed  $serialize_type
     * @param  array  $heder_struct
     * @param  string $pack_length_key
     * @throws \Exception
     * @return mixed
     */
    public function enpack($data, $header, array $header_struct = [], $pack_length_key ='length', $serialize_type = self::DECODE_JSON) {
        if(empty($header_struct)) {
            throw new \Exception('you must set the $header_struct');
        }
        $body = $this->encode($data, $serialize_type);
        $bin_header_data = '';
        if(!isset($header[$pack_length_key])) {
            $header[$pack_length_key] = '';
        }
        foreach($header_struct as $key=>$value) {
            if(isset($header[$key])) {
                // 计算包体长度
                if($key == $pack_length_key) {
                    $bin_header_data .= pack($value, strlen($body));
                }else {
                    // 其他的包头
                    $bin_header_data .= pack($value, $header[$key]);
                }
            }
        }

        return $bin_header_data . $body;
    }

    /**
     * depack
     * @param   mixed $data
     * @return  array
     */
    public function depack($data) {
        $unpack_length_type = $this->setUnpackLengthType();
        $package_body_offset = $this->client_pack_setting['package_body_offset'];
        $header = unpack($unpack_length_type, mb_strcut($data, 0, $package_body_offset, 'UTF-8'));
        $body_data = json_decode(mb_strcut($data, $package_body_offset, null, 'UTF-8'), true);
        return [$header, $body_data];
    }

    /**
     * setUnPackLengthType  设置unpack头的类型
     * @return   string
     */
    public function setUnpackLengthType() {
        $pack_length_type = '';
        if($this->client_header_struct) {
            foreach($this->client_header_struct as $key=>$value) {
                $pack_length_type .= ($value.$key).'/';
            }
        }
        $pack_length_type = trim($pack_length_type, '/');
        return $pack_length_type;
    }

    /**
     * encode 数据序列化
     * @param   mixed   $data
     * @param   int     $seralize_type
     * @throws \Exception
     * @return  string
     */
    public function encode($data, $serialize_type = self::DECODE_JSON) {
        if(is_string($serialize_type)) {
            $serialize_type = strtolower($serialize_type);
            $serialize_type = self::SERIALIZE_TYPE[$serialize_type];
        }
        switch($serialize_type) {
            // json
            case 1:
                return json_encode($data, JSON_UNESCAPED_UNICODE);
                break;
            // serialize
            case 2:
                return serialize($data);
                break;
            case 3;
                // swoole
                return \Swoole\Serialize::pack($data);
                break;
            default:
                $this->setStatusCode(RpcClientConst::ERROR_CODE_ENPACK);
                throw new \Exception("enpack error,may be serialize_type setted error", 1);
        }
    }

    /**
     * decode 数据反序列化
     * @param    string   $data
     * @param    mixed    $unseralize_type
     * @throws   \Exception
     * @return   mixed
     */
    public function decode($data, $unserialize_type = self::DECODE_JSON) {
        if(is_string($unserialize_type)) {
            $unserialize_type = strtolower($unserialize_type);
            $unserialize_type = self::SERIALIZE_TYPE[$unserialize_type];
        }
        switch($unserialize_type) {
            // json
            case 1:
                return json_decode($data, true);
                break;
            // serialize
            case 2:
                return unserialize($data);
                break;
            case 3;
                // swoole
                return \Swoole\Serialize::unpack($data);
                break;
            default:
                $this->setStatusCode(RpcClientConst::ERROR_CODE_DEPACK);
                throw new \Exception("depack error,may be serialize_type setted error", 1);
        }
    }

    /**
     * enpackeof eof协议封包,包体中不能含有eof的结尾符号，否则出错
     * @param  mixed $data
     * @param  int   $seralize_type
     * @param  string $eof
     * @return string
     */
    public function enpackeof($data, $serialize_type = self::DECODE_JSON, $eof ='') {
        if(empty($eof)) {
            $eof = $this->pack_eof;
        }
        if($this->server_serialize_type) {
            $serialize_type = $this->server_serialize_type;
        }
        $data = $this->encode($data, $serialize_type).$eof;

        return $data;
    }

    /**
     * depackeof  eof协议解包,每次收到一个完整的包
     * @param   string  $data
     * @param   int     $unseralize_type
     * @return  mixed
     */
    public function depackeof($data, $unserialize_type = self::DECODE_JSON) {
        if($this->client_serialize_type) {
            $unserialize_type = $this->client_serialize_type;
        }
        return $this->decode($data, $unserialize_type);
    }

    /**
     * close 关闭
     * @return
     */
    public function close($isforce = false) {
        if(method_exists($this->client, 'close')) {
            $this->client->close($isforce);
        }
        $this->disConnect();
    }

    /**
     * __destruct
     */
    public function __destruct() {
        RpcClientManager::getInstance()->destroyClientServicePackData($this->group_multi_id);
    }

    /**
     * __get
     * @param  string  $name
     * @return mixed
     */
    public function __get(string $name) {
        if(in_array($name, ['status_code', 'code'])) {
            return $this->getStatusCode();
        }
        return null;
    }

}