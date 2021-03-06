<?php
namespace Rpc\Tests;

use Rpc\Client\RpcClientManager;

class controller {
	public function test() {
		$callable = ['RpcService\Coms\Book\BookmanageService', 'test'];
		$params = ['content'=>'hhhhhhhhhhhhhhhh'];
		$header = ['length'=>'', 'version'=>'1.0.1', 'name'=>'bingcool'];

		$client1 = RpcClientManager::getInstance()->getServices('productService')->buildHeaderRequestId($header)->waitCall($callable, $params);

		$callable = ['RpcService\Coms\Book\BookmanageService', 'test'];
		$params = ['content'=>'hhhhhhhhhhhhhhhh'];
		$header = ['length'=>'', 'version'=>'1.0.1', 'name'=>'bingcool'];

		$client2 = RpcClientManager::getInstance()->getServices('productService')->buildHeaderRequestId($header)->waitCall($callable, $params);

		$res =  RpcClientManager::getInstance()->multiRecv([$client1, $client2]);

		//var_dump($res);


		$callable = ['RpcService\Coms\Book\BookmanageService', 'test'];
		$params = ['content'=>'hhhhhhhhhhhhhhhh'];
		$header = ['length'=>'', 'version'=>'1.0.1', 'name'=>'bingcool'];

		$client3 = RpcClientManager::getInstance()->getServices('productService');
		$client3->buildHeaderRequestId($header)->waitCall($callable, $params);
		$res1 = $client3->waitRecv(20);
//		var_dump($res1);
//
//		var_dump($client3->code);
//		var_dump($client3->getResponsePackHeader());
//		var_dump($client3->getResponsePackBody());

        $callable = ['RpcService\Coms\Book\BookmanageService', 'test'];
        $params = ['content'=>'hhhhhhhhhhhhhhhh'];
        $header = ['length'=>'', 'version'=>'1.0.1', 'name'=>'bingcool'];
        $client7 = RpcClientManager::getInstance()->getPersistentServices('productService', 'mytest')->buildHeaderRequestId($header)->waitCall($callable, $params);
        $res1 = $client7->waitRecv(20);
        //var_export('1');
        var_dump($client7->code);
        var_dump($res1);
	}
}