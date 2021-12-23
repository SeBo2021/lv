<?php
/**
 * 大白鲨支付查询
 */

namespace App\Console\Commands;

use App\Models\CommBbs;
use App\Models\CommFocus;
use App\Models\Order;
use App\Models\PayLog;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PayTrait;
use App\TraitClass\PHPRedisTrait;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class DBSQuery
 * @package App\Console\Commands
 */
class FocusNotice extends Command
{
    use PayTrait;
    use ApiParamsTrait;
    use PHPRedisTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'focus_notice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '社区最新文章通知';

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
     */
    public function handle(): bool
    {
        $this->info(lang('开始处理关注通知'));
        $redis = $this->redis();
        try {
            $data = CommBbs::query()->where(['focus_notice' => 0])->orderBy('id')
                ->get()?->toArray();
            array_map(function ($item) use ($redis) {
                $lostPostTimeKey = "last_post_{$item['author_id']}";
                $timeStamp = strtotime($item['created_at']);
                if ($timeStamp < ($redis->get($lostPostTimeKey) ?: 0)) {
                    return;
                }
                $redis->set($lostPostTimeKey, $timeStamp);
                $focus = CommFocus::where(['to_user_id' => $item['author_id']])->get()?->toArray();
                foreach ($focus as $v) {
                    $keyMe = "status_me_focus_{$v['user_id']}";
                    $redis->set($keyMe, $timeStamp);
                }
                CommBbs::query()->where(['id' => $item['id']])->update(['focus_notice' => 1]);
            }, $data);
        } catch (Exception $e) {
            Log::info('focus_notice_error===', [$e]);
        }
        $this->info(lang('操作成功'));
        return true;
    }
}
