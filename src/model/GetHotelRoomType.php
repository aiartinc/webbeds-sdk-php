<?php
/**
 * Created by PhpStorm.
 * User: Tomeu
 * Date: 11/4/2015
 * Time: 8:43 PM
 */
namespace webbeds\hotel_api_sdk\model;

use webbeds\hotel_api_sdk\model\ApiModel;

/**
 * Class GetHotelRoomTypes
 * @package webbeds\hotel_api_sdk\model
 * @property string userName User Name to use webBeds API
 * @property string password Password to use webBeds API
 */
class GetHotelRoomType extends ApiModel
{
    /**
     * GetHotelRoomTypes constructor.
     * @property string userName User Name to use webBeds API
     * @property string password Password to use webBeds API
     */
    public function __construct(array $data=null)
    {
        $this->validFields =
            [   "id" => "string",
                "dataType" => "string",
                "rooms" => "array",
                "roomType" => "string",
                "sharedRoom" => "string",
                "sharedFacilities" => "string"
            ];
             
            if ($data !== null)
            {
                $this->fields['id'] = $data['roomtype.ID'];
                //print_r($data);
                //TODO: resolve problem with namespace cannot be access.
                //$this->fields['dataType'] = $data['xsi'];
                $this->fields['dataType'] = 'StaticRoomTypeWithRooms';
                $this->fields['rooms'] = empty($data['rooms']) ? new Rooms([]): new Rooms($data['rooms']);             
                $this->fields['roomType'] = $data['room.type'];
                $this->fields['sharedRoom'] = $data['rooms'];
                $this->fields['sharedFacilities'] = $data['sharedFacilities'];
            }
    }
}