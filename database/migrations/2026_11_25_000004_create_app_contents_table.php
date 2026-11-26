<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();           // privacy_policy | about
            $table->string('title');
            $table->json('sections');                   // [{title, body}, ...]
            $table->timestamps();
        });

        // القيم الافتراضية (نفس المحتوى الذي كان ثابتاً في التطبيق)
        DB::table('app_contents')->insert([
            [
                'key' => 'privacy_policy',
                'title' => 'سياسة الخصوصية',
                'sections' => json_encode([
                    ['title' => 'المعلومات التي نجمعها', 'body' => 'نجمع المعلومات التي تزوّدنا بها عند إنشاء حسابك (الاسم، رقم الهاتف، البريد الإلكتروني، المحافظة والقضاء)، إضافةً إلى بيانات النباتات التي تضيفها (الموقع الجغرافي، الصور، الوصف).'],
                    ['title' => 'كيف نستخدم معلوماتك', 'body' => 'نستخدم بياناتك لتشغيل خدمات التطبيق: توثيق النباتات وعرضها على الخريطة، متابعة الري والبلاغات، والتواصل معك عبر رمز التحقق. لا نبيع بياناتك الشخصية لأي طرف ثالث.'],
                    ['title' => 'الموقع الجغرافي', 'body' => 'نطلب إذن الوصول لموقعك فقط عند إضافة نبتة لتحديد مكانها بدقة. يمكنك رفض الإذن وإدخال الموقع يدويًا أو اختياره من الخريطة.'],
                    ['title' => 'الصور', 'body' => 'الصور التي ترفعها تُستخدم لعرض النباتات داخل التطبيق ولوحة الإدارة. تأكّد من عدم رفع صور تحتوي على بيانات شخصية حسّاسة.'],
                    ['title' => 'أمان البيانات', 'body' => 'نتّخذ إجراءات معقولة لحماية بياناتك. تُرسل كلمات المرور مُعمّاة ولا تُخزَّن كنص صريح.'],
                    ['title' => 'حقوقك', 'body' => 'يمكنك تعديل بيانات حسابك أو حذف نباتاتك في أي وقت من داخل التطبيق. للتواصل بخصوص الخصوصية، راجع إدارة المنصة.'],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'about',
                'title' => 'عن التطبيق',
                'sections' => json_encode([
                    ['title' => 'نبتة', 'body' => 'منصّة مجتمعية لتوثيق ومتابعة الغطاء النباتي في مدينتك. سجّل نباتاتك، تابع نموّها على الخريطة، وساهم مع مجتمعك في بيئة أكثر خضرة.'],
                    ['title' => 'الإصدار', 'body' => '1.0.0'],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_contents');
    }
};
