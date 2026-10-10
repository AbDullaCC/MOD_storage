<?php

namespace Database\Seeders;

use App\Models\Addition;
use App\Models\Out;
use App\Models\ProductIn;
use App\Models\User;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo inventory is only available locally or in tests.');
        }
        if (ProductIn::exists()) {
            throw new RuntimeException('Demo inventory requires an empty inventory. Use the backed-up local reset script.');
        }
        $admin = User::where('role', 'admin')->where('is_active', true)->firstOrFail();
        $employee = User::where('role', 'operator')->where('is_active', true)->firstOrFail();
        $service = app(InventoryService::class);
        $clock = now()->subDays(12)->startOfDay()->addHours(9);
        $previousClock = Carbon::getTestNow();
        $step = function (callable $operation) use ($clock) {
            Carbon::setTestNow($clock->copy());
            $result = $operation();
            $clock->addHours(5);

            return $result;
        };
        $create = function (string $name, string $category, int $quantity, string $brand, string $model, string $description, ?User $actor = null) use ($step, $service, $employee) {
            return $step(fn () => $service->createItem([
                'name' => $name, 'category' => $category, 'quantity' => $quantity,
                'manufacturer' => $brand, 'model_type' => $model,
                'serial_number' => 'DEMO-'.str_pad((string) (ProductIn::count() + 1), 3, '0', STR_PAD_LEFT),
                'reciever' => 'أمين المستودع', 'description' => $description, 'added_at' => now(),
            ], $actor ?? $employee));
        };
        $movement = function (string $class, ProductIn $item, int $quantity, string $place, string $note, ?User $actor = null) use ($step, $service, $employee) {
            return $step(fn () => $service->createMovement($class, [
                'product_in_id' => $item->id, 'quantity' => $quantity, 'date' => now()->subHour(),
                $class === Out::class ? 'destination' : 'source' => $place, 'note' => $note,
            ], $actor ?? $employee));
        };

        try {
            $laptop = $create('حاسوب محمول Dell Latitude', 'حواسيب', 20, 'Dell', 'Latitude 5440', 'أجهزة مخصصة لتجهيز الموظفين الجدد.');
            $toner = $create('حبر طابعة HP 59A', 'أحبار', 12, 'HP', 'CF259A', 'عبوات حبر أصلية للطابعات المكتبية.');
            $cable = $create('كابل شبكة بطول 3 أمتار', 'كابلات', 40, 'D-Link', 'Cat6', 'كابلات جاهزة لتوصيل نقاط الشبكة.');
            $mouse = $create('فأرة لاسلكية Logitech', 'ملحقات', 18, 'Logitech', 'M185', 'ملحقات احتياطية للمكاتب.');
            $keyboard = $create('لوحة مفاتيح عربية', 'ملحقات', 10, 'Logitech', 'K120', 'صنف لم تسجل عليه حركات؛ يمكن تجربة تعديل بياناته وكميته الأولية.');
            $monitor = $create('شاشة سامسونغ', 'شاشات', 8, 'Samsung', 'S24R350', 'شاشات للعمل المكتبي بقياس 24 بوصة.');
            $wrong = $create('طابعة أُدخلت مرتين', 'طابعات', 3, 'Canon', 'LBP6030', 'إدخال مكرر سيُلغى قبل تسجيل أي حركة.');
            $router = $create('راوتر فرع قديم', 'شبكات', 3, 'TP-Link', 'Archer C6', 'تم توزيع كامل الكمية وانتهى التعامل مع هذا الصنف.');
            $ups = $create('مزود طاقة احتياطي UPS', 'طاقة', 2, 'APC', 'BX950', 'صنف يعاد تفعيله عند وصول دفعة جديدة.');
            $switch = $create('سويتش شبكة 8 منافذ', 'شبكات', 4, 'TP-Link', 'TL-SG108', 'نفد المخزون والصنف ما زال نشطاً لاستقبال توريد جديد.');
            $ssd = $create('قرص تخزين SSD سعة 500 GB', 'تخزين', 12, 'Kingston', 'A400', 'مخزون منخفض يحتاج إلى إعادة طلب.');
            $create('جهاز عرض Epson', 'أجهزة عرض', 3, 'Epson', 'EB-X49', 'صنف أنشأه المدير؛ الموظف يستطيع السحب والإضافة ولا يستطيع تعديل بيانات الصنف.', $admin);
            $server = $create('خادم ملفات صغير', 'خوادم', 1, 'Dell', 'PowerEdge T350', 'تجربة سحب موظف وإلغائه بواسطة المدير.', $admin);
            $hdmi = $create('كابل HDMI بطول مترين', 'كابلات', 20, 'UGREEN', 'HDMI 2.0', 'تجربة تصحيح الصنف المختار في حركة دون تغيير الكمية.');
            $usb = $create('كابل USB-C', 'كابلات', 25, 'Anker', 'PowerLine', 'كابلات شحن وبيانات.');
            $legacy = $step(fn () => ProductIn::create([
                'name' => 'كرسي مكتب من السجلات السابقة', 'category' => 'أثاث', 'quantity' => 6,
                'manufacturer' => 'محلي', 'model_type' => 'كرسي مكتبي', 'reciever' => 'المستودع الرئيسي',
                'description' => 'سجل قديم لا يتضمن اسم المستخدم أو رصيداً تاريخياً محفوظاً.', 'added_at' => now(),
            ]));
            $step(fn () => $legacy->outs()->create(['quantity' => 2, 'destination' => 'الأرشيف', 'date' => now(), 'note' => 'حركة سابقة قبل تفعيل سجل التدقيق.']));

            $movement(Addition::class, $laptop, 5, 'شركة توريد الحواسيب', 'فاتورة توريد رقم 104؛ فحص الأجهزة عند الاستلام.');
            $laptopOut = $movement(Out::class, $laptop, 6, 'المكتب الفني', 'تجهيز ستة موظفين جدد؛ استلمها أحمد خالد.');
            $tonerIn = $movement(Addition::class, $toner, 10, 'مورد المستلزمات', 'فاتورة رقم 208؛ عشر عبوات مغلقة.');
            $movement(Out::class, $toner, 8, 'الإدارة المالية', 'تسليم شهري للطابعات؛ استلمتها سارة محمود.');
            $badCableOut = $movement(Out::class, $cable, 15, 'فرع المزة', 'أدخلت الكمية قبل مراجعة سند التسليم.');
            $badMouseIn = $movement(Addition::class, $mouse, 12, 'مستودع المورد', 'كمية أولية من إشعار التوريد.');
            $movement(Out::class, $router, 3, 'الفروع الخارجية', 'توزيع آخر ثلاثة أجهزة؛ المخزون أصبح صفراً.');
            $movement(Out::class, $ups, 2, 'غرفة الخوادم', 'تسليم كامل الرصيد لتأمين الطاقة.');
            $movement(Out::class, $switch, 4, 'فرع حلب', 'تركيب شبكة الفرع الجديد.');
            $movement(Out::class, $ssd, 11, 'قسم الصيانة', 'ترقية أجهزة قديمة؛ بقي قرص واحد.');
            $serverOut = $movement(Out::class, $server, 1, 'غرفة الشبكة', 'تسليم مؤقت لمشروع لم يعتمد بعد.');
            $wrongOut = $movement(Out::class, $hdmi, 5, 'قاعة التدريب', 'تم اختيار كابل HDMI بالخطأ؛ المطلوب USB-C.');
            $wrongIn = $movement(Addition::class, $usb, 7, 'شركة الكابلات', 'تم اختيار USB-C بالخطأ؛ التوريد لكابلات HDMI.');

            $step(fn () => $service->updateItem($monitor->id, ['name' => 'شاشة Samsung مقاس 24 بوصة', 'description' => 'شاشة IPS بدقة Full HD مع منفذ HDMI.'], 'توضيح المقاس والمواصفات بناءً على بطاقة الصنف.', $employee));
            $step(fn () => $service->updateMovement(Out::class, $laptopOut->id, ['destination' => 'قسم الموارد البشرية', 'note' => 'تجهيز ستة موظفين جدد؛ استلمتها ريم حسن وفق سند 301.'], 'تصحيح الجهة المستلمة واسم المستلم من سند التسليم.', $employee));
            $step(fn () => $service->updateMovement(Addition::class, $tonerIn->id, ['source' => 'شركة الشام للمستلزمات'], 'تصحيح اسم المورد وفق الفاتورة الأصلية.', $employee));
            $step(fn () => $service->cancel(Out::class, $badCableOut->id, 'الكمية الصحيحة عشرة كابلات؛ إلغاء السحب وإعادة إدخاله.', $employee));
            $movement(Out::class, $cable, 10, 'فرع المزة', 'سند التسليم المصحح؛ عشرة كابلات فقط.');
            $step(fn () => $service->cancel(Addition::class, $badMouseIn->id, 'وصلت ثماني قطع فقط؛ إلغاء الإضافة الخاطئة.', $employee));
            $movement(Addition::class, $mouse, 8, 'مستودع المورد', 'ثماني قطع بعد المطابقة مع الاستلام الفعلي.');
            $keyboard = $step(fn () => $service->updateItem($keyboard->id, ['quantity' => 16], 'تصحيح الكمية الأولية بعد إعادة العد قبل أي حركة.', $employee));
            $step(fn () => $service->updateItem($keyboard->id, ['quantity' => 14], 'استبعاد قطعتين لم تدخلا المستودع؛ تصحيح نهائي قبل الحركات.', $employee));
            $step(fn () => $service->cancelItem($wrong->id, 'إدخال مكرر لصنف غير مستلم؛ إلغاء السجل بالكامل.', $employee));
            $step(fn () => $service->archive($router->id, 'انتهى توزيع الأجهزة ولن يطلب هذا الموديل مجدداً.', $admin));
            $step(fn () => $service->archive($ups->id, 'نفد المخزون وتم إيقاف التوريد مؤقتاً.', $admin));
            $step(fn () => $service->archive($ups->id, 'وصل توريد جديد؛ إعادة تفعيل الصنف.', $admin, true));
            $movement(Addition::class, $ups, 4, 'شركة أنظمة الطاقة', 'دفعة جديدة بعد إعادة تفعيل الصنف؛ فاتورة 415.');
            $step(fn () => $service->cancel(Out::class, $serverOut->id, 'ألغى المدير التسليم لأن المشروع لم يعتمد؛ أعيد الجهاز.', $admin));
            $step(fn () => $service->updateMovement(Out::class, $wrongOut->id, ['product_in_id' => $usb->id], 'تصحيح الصنف المختار؛ التسليم خمسة كابلات USB-C.', $employee));
            $step(fn () => $service->updateMovement(Addition::class, $wrongIn->id, ['product_in_id' => $hdmi->id], 'تصحيح الصنف المختار وفق فاتورة توريد HDMI.', $employee));
        } finally {
            Carbon::setTestNow($previousClock);
        }
    }
}
