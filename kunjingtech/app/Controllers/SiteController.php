<?php
/**
 * 永州堃景科技 · 企业官网 — 站点控制器
 * 演示 KunJingPHP：路由分发 / 模板渲染 / 验证器 / 日志 / ORM（留言存储）
 */

namespace Site\Controllers;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use KunJing\Framework\Facade\View;
use KunJing\Framework\Facade\Log;
use KunJing\Framework\Validate\Validator;
use Site\Models\Message;

class SiteController
{
    /* ============ 站点数据 ============ */

    private function stats(): array
    {
        return [
            ['num' => '300+',   'lab' => '服务客户'],
            ['num' => '1200+',  'lab' => '交付项目'],
            ['num' => '50+',    'lab' => '技术团队'],
            ['num' => '8年',    'lab' => '行业经验'],
        ];
    }

    /** 主营业务：电商代运营（旗舰）+ 电商客服外包 + 9 类开发 */
    private function servicesData(): array
    {
        return [
            ['ic' => '🛍️', 'title' => '电商代运营', 'desc' => '天猫 / 淘宝 / 京东 / 拼多多 / 抖音 / 快手 / 视频号 / 小红书 / 唯品会等国内平台店铺代运营，以及 Amazon / TikTok Shop / 速卖通 / Shopee / Lazada / eBay / Temu 跨境平台代运营；涵盖开店装修、活动策划、推广投放、直播带货、客服接待、订单与数据全链路。'],
            ['ic' => '🎧', 'title' => '电商客服外包', 'desc' => '承接淘宝 / 京东 / 抖音 / 拼多多等平台售前售后客服外包，7×24 响应式服务，提升转化与店铺评分。'],
            ['ic' => '🌐', 'title' => 'WEB 网站开发', 'desc' => '以自研 KunJingPHP 框架为核心，交付企业官网、行业门户与各类业务系统，安全可控。'],
            ['ic' => '📱', 'title' => 'H5 网站开发', 'desc' => '跨移动端 H5 活动页、营销页与轻应用，即开即用，快速触达用户。'],
            ['ic' => '💻', 'title' => 'PC 客户端开发', 'desc' => 'Windows / macOS 桌面客户端，Electron 与原生方案灵活选型。'],
            ['ic' => '🤖', 'title' => '安卓应用开发', 'desc' => 'Android 原生与跨端 App 开发，覆盖上架与后续迭代全流程。'],
            ['ic' => '🍎', 'title' => 'iOS 应用开发', 'desc' => 'iPhone / iPad 应用开发与上架（App Store），含证书与发布支持。'],
            ['ic' => '🍏', 'title' => 'macOS 应用开发', 'desc' => '桌面生产力与工具类应用，适配 Apple 生态体验。'],
            ['ic' => '🔷', 'title' => '鸿蒙应用开发', 'desc' => 'HarmonyOS 原生应用与元服务开发，抢占全新生态红利。'],
            ['ic' => '🟢', 'title' => '小程序开发', 'desc' => '微信 / 支付宝 / 抖音 / 百度多端小程序，一套业务多端覆盖。'],
            ['ic' => '🧩', 'title' => '多端整体方案', 'desc' => 'WEB + H5 + 小程序 + App 一体化交付，统一后台与数据。'],
        ];
    }

    /** 电商代运营：国内 + 跨境平台矩阵（用于首页旗舰展示） */
    private function agencyPlatforms(): array
    {
        return [
            'domestic' => ['天猫', '淘宝', '京东', '拼多多', '抖音', '快手', '视频号', '小红书', '唯品会'],
            'cross'    => ['Amazon', 'TikTok Shop', '速卖通', 'Shopee', 'Lazada', 'eBay', 'Temu'],
        ];
    }

    /** 产品源码矩阵：15 类 */
    private function productsData(): array
    {
        return [
            ['ic' => '🏢', 'title' => '企业官网', 'desc' => '品牌官网 / 集团门户模板与定制。'],
            ['ic' => '🛒', 'title' => '商城系统', 'desc' => 'B2C / B2B / 多商户商城解决方案。'],
            ['ic' => '💰', 'title' => '金融理财', 'desc' => '理财 / 配资 / 数字资产展示系统。'],
            ['ic' => '📡', 'title' => '直播源码', 'desc' => '娱乐 / 电商直播源码与信令方案。'],
            ['ic' => '🤝', 'title' => '淘客源码', 'desc' => '返利 / 优惠券 / 导购分佣系统。'],
            ['ic' => '🎓', 'title' => '培训教育', 'desc' => '在线课程 / 网校 / 知识付费平台。'],
            ['ic' => '📚', 'title' => '小说源码', 'desc' => '小说阅读 / 漫画 / 听书社区。'],
            ['ic' => '🖼️', 'title' => '图片源码', 'desc' => '图库 / 壁纸 / 设计素材社区。'],
            ['ic' => '⛓️', 'title' => '区块源码', 'desc' => '区块链 / Web3 / 数字藏品应用。'],
            ['ic' => '🚀', 'title' => '运营引流', 'desc' => '裂变 / 分销 / 短链 / 社群工具。'],
            ['ic' => '🎮', 'title' => 'H5 源码', 'desc' => 'H5 游戏 / 互动营销 / 小游戏。'],
            ['ic' => '💳', 'title' => '支付充值', 'desc' => '聚合支付 / 充值 / 会员卡系统。'],
            ['ic' => '🎨', 'title' => '素材模板', 'desc' => '模板市场 / PPT / 设计素材。'],
            ['ic' => '🏗️', 'title' => '行业整站', 'desc' => '医疗 / 旅游 / 房产 / 汽车整站。'],
            ['ic' => '🎬', 'title' => '影视视频', 'desc' => '影视 / 短剧 / 点播平台。'],
        ];
    }

    private function advantages(): array
    {
        return [
            ['ic' => '⚙️', 'title' => '自研框架底座', 'desc' => '100% 自研 KunJingPHP，安全可控、无外部授权与合规风险。'],
            ['ic' => '🌐', 'title' => '全平台覆盖', 'desc' => 'WEB / H5 / 小程序 / 安卓 / iOS / macOS / 鸿蒙，一套团队贯通。'],
            ['ic' => '🔧', 'title' => '全流程交付', 'desc' => '需求梳理→设计→开发→测试→上线→运维，一站式闭环。'],
            ['ic' => '🛡️', 'title' => '源码级服务', 'desc' => '源码 + 二次开发 + 部署 + 售后，透明、可审计、可沉淀。'],
            ['ic' => '🎧', 'title' => '客服外包能力', 'desc' => '专业电商客服团队，降低企业用人与培训成本。'],
            ['ic' => '🛍️', 'title' => '电商代运营实力', 'desc' => '国内 + 跨境全平台店铺代运营，从开店到爆单一站式陪跑，懂运营更懂技术。'],
            ['ic' => '💡', 'title' => '性价比保障', 'desc' => '永州本地团队，成本可控、沟通高效、响应迅速。'],
        ];
    }

    private function casesData(): array
    {
        return [
            ['cls' => 'a', 'title' => '某连锁品牌 · 官网 + 商城', 'desc' => '品牌官网与 B2C 商城一体化交付，上线首月 GMV 提升 35%。', 'tag' => 'WEB + 商城'],
            ['cls' => 'b', 'title' => '房产中介 · 行业整站 SaaS', 'desc' => '多门店整站系统，覆盖全国 200+ 门店，统一管理房源与客资。', 'tag' => '行业整站'],
            ['cls' => 'c', 'title' => '娱乐直播源码项目', 'desc' => '高并发直播间架构，单场峰值 5 万人在线稳定流畅。', 'tag' => '直播源码'],
            ['cls' => 'd', 'title' => '短剧影视点播平台', 'desc' => 'H5 + App 双端协同，日活跃用户突破 12 万。', 'tag' => '影视视频'],
            ['cls' => 'a', 'title' => '小说阅读社区', 'desc' => '小说 + 漫画 + 听书三合一，累计月活 80 万。', 'tag' => '小说源码'],
            ['cls' => 'b', 'title' => '聚合支付与会员系统', 'desc' => '打通 6 大支付渠道，支持 T+0 结算与多级会员体系。', 'tag' => '支付充值'],
        ];
    }

    private function contactInfo(): array
    {
        return [
            'address' => '湖南省永州市冷水滩区（示例地址，请按实际修改）',
            'phone'   => '0746-0000000（示例，请按实际修改）',
            'mobile'  => '137-0000-0000（示例，请按实际修改）',
            'email'   => 'service@kunjingkeji.cn',
            'wechat'  => 'kunjingkeji',
            'domain'  => 'kunjingkeji.cn',
        ];
    }

    private function navTypes(): array
    {
        return ['网站开发', '小程序开发', 'H5 开发', 'App 开发', '客服外包', '其他咨询'];
    }

    /* ============ 页面 ============ */

    public function home()
    {
        return View::render('home', [
            'active'      => 'home',
            'stats'      => $this->stats(),
            'agency'     => $this->agencyPlatforms(),
            'services'   => array_slice($this->servicesData(), 0, 6),
            'products'   => array_slice($this->productsData(), 0, 8),
            'advantages' => $this->advantages(),
        ]);
    }

    public function about()
    {
        return View::render('about', [
            'active'    => 'about',
            'stats'     => $this->stats(),
            'advantages'=> $this->advantages(),
        ]);
    }

    public function services()
    {
        return View::render('services', [
            'active'   => 'services',
            'services' => $this->servicesData(),
        ]);
    }

    public function products()
    {
        return View::render('products', [
            'active'   => 'products',
            'products' => $this->productsData(),
        ]);
    }

    public function cases()
    {
        return View::render('cases', [
            'active' => 'cases',
            'cases'  => $this->casesData(),
        ]);
    }

    public function contact(Request $request)
    {
        return View::render('contact', [
            'active'      => 'contact',
            'info'        => $this->contactInfo(),
            'types'       => $this->navTypes(),
            'sent'        => $request->get('sent') === '1',
            'errors'      => [],
            'old'         => [],
        ]);
    }

    /**
     * 提交留言：服务端校验 -> 优先存库（contact_messages），失败回退文件存储。
     */
    public function contactPost(Request $request)
    {
        $data = $request->only(['name', 'phone', 'email', 'type', 'content']);

        $validator = Validator::make($data, [
            'name'    => 'required|min:2|max:30',
            'phone'   => 'required|regex:/^[0-9+\-()\s]{6,20}$/',
            'email'   => 'required|email|max:120',
            'type'    => 'required|max:60',
            'content' => 'required|min:5|max:1000',
        ], [
            'name.required'    => '请填写您的称呼',
            'name.min'         => '称呼至少 2 个字',
            'phone.required'   => '请填写联系电话',
            'phone.regex'      => '联系电话格式不正确',
            'email.required'   => '请填写邮箱',
            'email.email'      => '邮箱格式不正确',
            'type.required'    => '请选择需求类型',
            'content.required' => '请填写您的需求描述',
            'content.min'      => '需求描述至少 5 个字',
        ]);

        if ($validator->fails()) {
            return View::render('contact', [
                'active'  => 'contact',
                'info'    => $this->contactInfo(),
                'types'   => $this->navTypes(),
                'sent'    => false,
                'errors'  => $validator->flatErrors(),
                'old'     => $data,
            ]);
        }

        $data['ip'] = $request->ip();
        $saved = $this->saveMessage($data);
        Log::info('收到网站留言', ['name' => $data['name'], 'type' => $data['type'], 'store' => $saved ? 'db' : 'file']);

        return Response::redirect('/contact?sent=1');
    }

    /**
     * 存储留言：优先数据库，异常时回退到文件（storage/messages.log，JSON 行）。
     */
    private function saveMessage(array $data): bool
    {
        try {
            Message::create($data);
            return true;
        } catch (\Throwable $e) {
            try {
                $line = json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
                $dir = __DIR__ . '/../../storage';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                @file_put_contents($dir . '/messages.log', $line, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e2) {
                Log::warning('留言文件存储失败：' . $e2->getMessage());
            }
            return false;
        }
    }
}
