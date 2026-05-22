<!DOCTYPE html>
<html lang="bn">
<body style="font-family: Arial, sans-serif; background-color: #f4f7f9; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 12px; border-top: 6px solid #f59e0b; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="background-color: #fffbeb; width: 60px; height: 60px; border-radius: 50%; display: inline-block; line-height: 60px; font-size: 30px;">👀</div>
        </div>

        <h2 style="color: #111827; text-align: center;">আপনার যোগাযোগের তথ্য সংগ্রহ করা হয়েছে!</h2>
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
            আসসালামু আলাইকুম,<br><br>
            আনন্দের সাথে জানানো যাচ্ছে যে, আমাদের ওয়েবসাইটের <strong>{{ $buyerName }}</strong> নামক একজন ইউজার আপনার বায়োডাটার <strong>(নং: {{ $biodataNo }})</strong> যোগাযোগের তথ্য সংগ্রহ করেছেন।
        </p>

        <p style="color: #4b5563; font-size: 15px; text-align: center; margin-top: 20px; background-color: #f3f4f6; padding: 10px; border-radius: 8px;">
            খুব শিঘ্রই হয়তো উক্ত পরিবারের পক্ষ থেকে আপনার অভিভাবকের সাথে যোগাযোগ করা হতে পারে। আল্লাহ আপনার জন্য উত্তম ফয়সালা করুন।
        </p>

        <div style="text-align: center; margin-top: 30px;">
            @if($buyerBiodataUrl)
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">আপনি চাইলে নিচের বাটনে ক্লিক করে উক্ত ইউজারের বায়োডাটাটি দেখে নিতে পারেন:</p>
                <a href="{{ $buyerBiodataUrl }}" style="background-color: #542875; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-right: 10px;">উনার বায়োডাটা দেখুন</a>
            @else
                <a href="{{ $dashboardUrl }}" style="background-color: #10b981; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;">ড্যাশবোর্ডে যান</a>
            @endif
        </div>
    </div>
</body>
</html>
