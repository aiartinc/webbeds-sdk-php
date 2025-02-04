<?php

/**
 * #%L
 * hotel-api-sdk
 * %%
 * Copyright (C) 2018-2019 Hamilton Wang
 * %%
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 2.1 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Lesser Public License for more details.
 *
 * You should have received a copy of the GNU General Lesser Public
 * License along with this program.  If not, see
 * <http://www.gnu.org/licenses/lgpl-2.1.html>.
 * #L%
 */

namespace webbeds\hotel_api_sdk;

use webbeds\hotel_api_sdk\model\AuditData;
use webbeds\hotel_api_sdk\types\ApiVersion;
use webbeds\hotel_api_sdk\types\ApiVersions;
use webbeds\hotel_api_sdk\types\HotelSDKException;
use webbeds\hotel_api_sdk\messages\ApiRequest;

use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Uri\UriFactory;

class HotelApiClient
{
    /**
     * @var apiUri Well formatted URI of service for Book
     */
    private $apiUri;
    /**
     * @var string Stores locally client password 
     */
    private $password;

    /**
     * @var string Stores locally client api key
     */
    private $userName;

    /**
     * @var Client HTTPClient object
     */
    private $httpClient;

    /**
     * @var Request Last sent request
     */
    private $lastRequest;
    /**
     * @var Response Last sent request
     */
    private $lastResponse;
    /**
     * @var string Last SDK Method
     */
    private $lastSdkMethod;
    /**
     * @var string lib used search or booking
     */
    private $lib;

    /**
     * HotelApiClient Constructor they initialize SDK Client.
     * @param string $url Base URL of hotel-api service.
     * @param string $userName Client userName
     * @param string $password
     * @param ApiVersion $version Version of Hotel API Interface
     * @param int $timeout HTTP Client timeout
     * @param string $adapter Customize adapter for http request
     */
    public function __construct($url, $userName, $password, ApiVersion $version, $lib, $timeout=30, $adapter=null)
    {
        $this->lastRequest = null;
        $this->userName = trim($userName);
        $this->password = trim($password);
        $this->httpClient = new Client();
        $this->lib = trim($lib);
        if($adapter!=null) {
            $this->httpClient->setOptions([
            		"adapter" => $adapter,
            		"timeout" => $timeout
            ]);
        }else{
            $this->httpClient->setOptions([
            		"timeout" => $timeout
            ]);
        }
        UriFactory::registerScheme("https","webbeds\\hotel_api_sdk\\types\\ApiUri");
        $this->apiUri = UriFactory::factory($url);
        $this->apiUri->prepare($version, $lib);
    }

    /**
     * @param $sdkMethod string Method request name.
     * @param $args array only specify a ApiHelper class type for encapsulate request arguments
     * @return ApiResponse Class of response. Each call type returns response class: For example BookingReq returns BookingResp
     * @throws HotelSDKException Specific exception of call
     */
    public function __call($sdkMethod, array $args=null)
    {
        $this->lastSdkMethod = $sdkMethod;
        $sdkClassReq = "webbeds\\hotel_api_sdk\\messages\\".$sdkMethod."Req";
        $sdkClassResp = "webbeds\\hotel_api_sdk\\messages\\".$sdkMethod."Resp";
        if (!class_exists($sdkClassReq) && !class_exists($sdkClassResp)){
            throw new HotelSDKException("$sdkClassReq or $sdkClassResp not implemented in SDK");
        }
        //if($sdkClassReq == "webbeds\\hotel_api_sdk\\messages\\BookingConfirmReq"){
        //	$req = new $sdkClassReq($this->apiUri, $args[0]);
        //}else{
	        if ($args !== null && count($args) > 0){
	            $req = new $sdkClassReq($this->apiUri, $args[0]);
	        } else {
	        	$req = new $sdkClassReq($this->apiUri);
            }
            
            //echo "req type: " . get_class($req). "\n";
        //}
        //return new $sdkClassResp($this->callApi($req));
        return $this->callApi($req);
    }

    /**
     * Generic API Call, this is a internal used method for sending all requests to RESTful webservice 
     * XML response and transforms to PHP-Array object.
     * @param ApiRequest $request API Abstract request helper for construct request
     * @return Object SimpleXMLElement Object
     * @throws HotelSDKException Calling exception, can capture remote server auditdata if exists.
     */
    private function callApi(ApiRequest $request)
    {
        try {
            $this->lastRequest = $request->prepare($this->userName, $this->password, $this->lib);
            $response = $this->httpClient->send($this->lastRequest);
            //$response = $this->httpClient->send();
            $this->lastResponse = $response;
        } catch (\Exception $e) {
            throw new HotelSDKException("Error accessing API: " . $e->getMessage());
        }
       // echo '--> getBody:' . $response->getBody();
        if ($response->getStatusCode() !== 200) {
           $auditData = null; $message=''; $errorResponse = null;
           if ($response->getBody() !== null) {
               try {
                   $root = $this->lastSdkMethod . "Result";
                   $errorResponse = simplexml_load_string( $response->getBody() );
                   //$auditData = new AuditData($errorResponse["auditData"]);
                   $message =$errorResponse[$root]["Error"]["ErrorType"].' '.$errorResponse[$root]["Error"]["Message"];
               } catch (\Exception $e) {
                   throw new HotelSDKException($response->getReasonPhrase().': '.$response->getBody());
               }
           }
            throw new HotelSDKException($response->getReasonPhrase().': '.$message, $auditData);
        }
        $resp = simplexml_load_string( mb_convert_encoding($response->getBody(),'UTF-8'));
        //print_r( '--> getBody simplexml_load_string:' . $json);
        return $resp;
    }

    /**
     * @return array ConvertXMLToNative convert XML Object to Native format
     */
    public function ConvertXMLToNative($xml_string, $sdkMethod)
    {
        $sdkClassResp = "webbeds\\hotel_api_sdk\\messages\\".$sdkMethod."Resp";
        $array = $this->ConvertXMLToArray2($xml_string);
        //print_r($array);
        return new $sdkClassResp($array);
    }
    
    /**
     * @return array ConvertSimpleXMLToNative convert XML Object to Native format
     */
    public function ConvertSimpleXMLToNative($xml_string, $root, $sdkMethod)
    {
        $sdkClassResp = "webbeds\\hotel_api_sdk\\messages\\".$sdkMethod."Resp";
        $data = $xml_string->hotels;
        //print_r($data);
        return new $sdkClassResp($data);
    }

    /**
     * @return array ConvertXMLToJson convert SimpleXMLElement Object to JSON format
     */
    public function ConvertXMLToJson($xml_string)
    {
        $json = json_encode( $xml_string );

        return $json;
    }

    /**
     * @return array ConvertXMLToArray convert XMl Object to Array format
     */
    public function ConvertXMLToArray($xml_string)
    {
        // sample
        //echo '--> acccessing data' .(string)$xml_string->languages->language[0]->asXml();
        //echo '--> array inside(before):';
        //print_r( $xml_string);
        $result = toArray ($xml_string);
        //echo '--> array inside(after):';
        //print_r($result);
        return $result;
    }


    /**
     * @return array ConvertXMLToArray2 convert XMl Object to Array format
     */
    public function ConvertXMLToArray2($xml_string)
    {
        $json = json_encode( $xml_string );
        $array = json_decode($json, TRUE);
        //echo $xml_string;

        return $array;
    }

    private function toArray(\SimpleXMLElement $xml) {
        
        $array = (array)$xml;
        //echo 'to array:';
        //print_r( $array);
        foreach ( array_slice($array, 0) as $key => $value ) {
            if ( $value instanceof SimpleXMLElement ) {
                $array[$key] = empty($value) ? NULL : $this->toArray($value);
            }
        }
        return $array;
    }

    function xml2array ( $xmlObject, $out = array () )
    {
        foreach ( (array) $xmlObject as $index => $node )
            $out[$index] = ( is_object ( $node ) ) ? $this->xml2array ( $node ) : $node;

        return $out;
    }

    /**
     * @return Request getLastRequest Returns entire raw request
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @return Response getLastResponse Returns entire raw response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @return Response getLastResponse Returns entire raw response
     */
    public function getLastSdkMethod()
    {
        return $this->lastSdkMethod;
    }
}