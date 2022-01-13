<?php

namespace App\TraitClass;

trait LoginTrait
{

    public $loginUserFields = ['id','account','channel_id','nickname','device_system','phone_number','promotion_code','avatar','sex','gold','balance','long_vedio_times','area_number'];

    public $loginRules = [
        'type' => 'required|integer|between:1,2',
        'did' => 'required|string',
        'dev' => 'required',
        'env' => 'required',
        'name' => 'nullable|string',
        'test' => 'nullable|boolean',
    ];

    public $createNickNames = [
        '孤独患者',
        '会飞的贼',
        '笨比熊',
        '花无邪',
        '一莺时',
        '长安忆',
        '放开那萌比',
        '乏味万千',
        '无恶不赦',
        '池鱼',
        '枯心人',
        '姑娘久等了',
        '何为美人',
        '善解人衣',
        '桃花扇',
        '勾勒相思',
        '酒自斟',
        '在水一方',
        '少女的医生',
        '琉璃心',
        '曾何时',
        '暴君与猫',
        '星星曲奇',
        '欲望作祟',
        '陪你单身',
    ];

    public function generateChatUrl(Array $user): array
    {
        $queryParam = http_build_query([
            'account' => $user['account'],
            'nickname' => $user['nickname'],
            'id' => $user['id']
        ]);
        $user['kf_url'] = 'https://vm.daneviolda.com/1wyai8j871j3z054rryao48w7g?'.$queryParam;
        return $user;
    }
}