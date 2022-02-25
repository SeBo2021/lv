<?php
/**
 * 大白鲨支付查询
 */

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PayLog;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\CJTrait;
use App\TraitClass\PayTrait;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\SimpleCache\InvalidArgumentException;
use App\ExtendClass\Random;

/**
 * Class CJQuery
 * @package App\Console\Commands
 */
class CJQuery extends Command
{
    use PayTrait;
    use ApiParamsTrait;
    use CJTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cj_query {order_id?} {process=true}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '长江支付定时查询订单';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return bool
     * @throws GuzzleException|InvalidArgumentException
     */
    public function handle(): bool
    {
        $payEnv = self::getPayEnv();
        $secret = json_decode($payEnv['CJ']['secret'],true);
        $md5Key = $secret['md5_key'];
        $privateKey = $secret['public_key'];

        $arguments = $this->arguments();
        $orderId = $arguments['order_id'];
        $process = $arguments['process'];
        $this->info(lang('开始查询订单'));
        try {
            $data = PayLog::query()->where(function ($query) use ($orderId) {
                if ($orderId) {
                    $query->where(['id' => $orderId]);
                }
                $query->where(['pay_method' => 2]);
            })->orderBy('id')
                ->get()?->toArray();
            array_map(function ($payInfo) use ($process, $privateKey, $md5Key,$payEnv) {
                if (($payInfo['status'] == 1) && $process) {
                    $this->info("订单已经支付,订单id{$payInfo['order_id']}");
                    return;
                }
                sleep(3);
                $orderInfo = Order::query()->find($payInfo['order_id']);
                if (!$orderInfo) {
                    throw new Exception("订单不存在");
                }
                $mercId = SELF::getPayEnv()['CJ']['merchant_id'];
                $input = [
                    'mercId' => $mercId,
                    'orderId' => strval($payInfo['number']),
                    'nonceStr' => Random::alnum('32'),
                ];
                $input['sign'] = $this->sign($input, $md5Key, $privateKey);
                Log::info('cj_query_params===', [$input]);//三方参数日志
                $response = (new Client([
                    // 'headers' => ['Content-Type' => 'multipart/form-data'],
                    'verify' => false,
                ]))->post($payEnv['CJ']['pay_url'], ['form_params' => $input])->getBody();
                Log::info('cj_query_response===', [$response]);//三方响应日志
                $content = json_decode($response, true);
                if ($process && ($content['msg']['status'] == 1)) {
                    DB::beginTransaction();
                    $this->orderUpdate($orderInfo->orderId ?? '', $response);
                    DB::commit();
                } else {
                    $this->info('查询结果为:' . $response);
                }
            }, $data);
        } catch (Exception $e) {
            DB::rollBack();
            Log::info('cj_query_error===', [$e]);
        }
        $this->info(lang('操作成功'));
        return true;
    }
}
