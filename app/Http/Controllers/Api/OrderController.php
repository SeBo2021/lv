<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberCard;
use App\Models\Order;
use App\Models\PayLog;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PayTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends PayBaseController
{
    use ApiParamsTrait,MemberCardTrait,PayTrait;

    /**
     * 订单创建接口
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function create(Request $request): mixed
    {
        // TODO: Implement pay() method.
        $params = ApiParamsTrait::parse($request->params ?? '');
        Validator::make($params, [
            'type' => [
                'required',
                'string',
                Rule::in(['1', '2','3']),
            ],
            'goods_id' => 'required|string',
            'method_id' => [
                'nullable',
                'string',
                Rule::in(['1', '2']),
            ],
            'forward' => 'nullable',
            'vid' => 'nullable',
            'time' => 'required|string',
            'pay_method' => [
                'nullable',
               // 'integer',
               // Rule::in([1, 2,3]),
            ],
        ])->validate();
        Log::info('order_create_params===',[$params]);//参数日志
        $goodsMethod = match ($params['type']) {
            '1' => 'getVipInfo',
            '2' => 'getGoldInfo',
            '3' => 'getGoodsInfo',
        };
        $goodsInfo = $this->$goodsMethod($params['goods_id']);
        $now = date('Y-m-d H:i:s', time());
        $user = $request->user();
        if($goodsMethod == 'getGoldInfo'){
            if($goodsInfo['user_type']==1 && (!$this->isVip($user))){
                return response()->json($this->format(-1, [], '无vip权限'));
            }
        }
        //是否有效打折
        $useRealValue = false;
        $realValue = $goodsInfo['real_value'] ?? 0;
        if($realValue > 0){
            $validPeriodTime = strtotime($user->created_at) + $goodsInfo['hours']*3600;
            if($validPeriodTime > time()){
                $useRealValue = true;
            }
            Log::info('===memberCard_diff_time==',[$user->created_at,$goodsInfo['hours']*3600,$validPeriodTime,time(),$useRealValue]);
        }
        if ($params['method_id']??false) {
            $amount = $goodsInfo[match ($params['method_id']) {
                '1' => 'zfb_fee',
                '2' => 'wx_fee',
            }];
        } else {
            $amount = $goodsInfo[match ($params['type']) {
                '1' => $useRealValue ? 'real_value' : 'value',
                '2' => 'money',
                '3' => 'gold',
            }];
        }
        try {
            $number = self::getPayNumber();
            $createData = [
                'remark' => json_encode($goodsInfo),
                'number' => $number,
                'type' => $params['type'],
                'type_id' => $params['goods_id'],
                'amount' => $amount,
                'uid' => $user->id,
                'channel_id' => $user->channel_id??0,
                'channel_pid' => $user->channel_pid??0,
                'status' => 0,
                'forward' => $params['forward'] ?? '',
                'vid' => $params['vid'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            Log::info('order_create_Data===',[$createData]);//参数日志
            DB::beginTransaction();
            // 创建订单
            $order = Order::query()->create($createData);
            $payMethod = $params['pay_method']??1;
            $payNumber = '';
            if ($params['pay_method'] == 0) {
                $payMethod = $this->getOwnCode($params['type'],$params['goods_id'],$params['method_id']);
                $payNumber = $this->getOwnNumber($params['type'],$params['goods_id'],$params['method_id']);
            }
            // 准备支付记录
            $pay = PayLog::query()->create([
                'order_id' => $order->id,
                'number' => $number,
                'request_info' => json_encode($params),
                'goods_info' => $params['goods_id'],
                'uid' => $request->user()->id,
                'status' => 0,
                'device_system' => $request->user()->device_system??1,
                'created_at' => $now,
                'pay_method' => $payMethod,
                'channel_code' => $payNumber,
                'updated_at' => $now,
            ]);
            DB::commit();
            $return = $this->format(0, ['pay_id' => $pay->id,'order_id'=>$order->id], '取出成功');
        } catch (Exception $e) {
            DB::rollBack();
            $return = $this->format($e->getCode(), [], $e->getMessage());
        }

        return response()->json($return);
    }

    /**
     * 订单查询接口
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function query(Request $request): JsonResponse
    {
        $params = ApiParamsTrait::parse($request->params ?? '');
        Validator::make($params, [
            'order_id' => 'required|string',
        ])->validate();
        try {
            $order = Order::query()->findOrFail($params['order_id']);
            $return = $this->format(0,$order,"取出成功");
        } catch (Exception $e){
            $return = $this->format(-1,new \stdClass,"取出失败");
        }
        return response()->json($return);
    }

    /**
     * 得到支付单号
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     * @throws Exception
     */
    public function orderPay(Request $request): JsonResponse
    {
        $params = ApiParamsTrait::parse($request->params ?? '');
        Validator::make($params, [
            'order_id' => 'required|string',
            'time' => 'required|string',
            'pay_method' => [
                'nullable',
               // 'integer',
              //  Rule::in([1, 2,3]),
            ],
            'method_id' => [
                'nullable',
                'string',
                Rule::in(['1', '2']),
            ],
            'amount' => [
                'nullable',
                'integer',
            ],
        ])->validate();
        Log::info('order_pay_params===',[$params]);//参数日志
        try {
            $orderModel = Order::query();
            if ($params['amount']??false) {
                $orderModel->where('id',$params['order_id'])->update(['amount'=>$params['amount']]);
            }
            $order = $orderModel->find($params['order_id'])?->toArray();

            if (!$order) {
                throw new Exception('订单不存在', -1);
            }
            if (1 == $order['status']) {
                throw new Exception('订单已经支付', -2);
            }
            $payLog = PayLog::query()->orderBy('id','desc')
                ->where([
                    'order_id'=>$params['order_id'],
                    'method_id' => $params['method_id']??1,
                    'status'=>'0',
                    ])->first()?->toArray();
            $payMethod = $params['pay_method']??1;
            $payNumber = '';
            if ($params['pay_method'] == 0) {
                $payMethod = $this->getOwnCode($order['type'],$order['type_id'],$params['method_id']);
                $payNumber = $this->getOwnNumber($order['type'],$order['goods_id'],$params['method_id']);
            }
            if ($payLog) {
                $payId = $payLog['id'];
            } else {
                $now = date('Y-m-d H:i:s', time());
                $payNew = PayLog::query()->create([
                    'order_id' => $order['id'],
                    'request_info' => json_encode($params),
                    'goods_info' => $order['type_id'],
                    'number' => self::getPayNumber(),
                    'uid' => $request->user()->id,
                    'status' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'pay_method' => $payMethod,
                    'channel_code' => $payNumber,
                    'method_id' => $params['method_id']??1,
                ]);
                $payId = $payNew['id'];
            }
            $return = $this->format(0, ['pay_id' => $payId], '取出成功');
        } catch (Exception $e) {
            $return = $this->format($e->getCode(), [], $e->getMessage());
        }
        return response()->json($return);
    }
}