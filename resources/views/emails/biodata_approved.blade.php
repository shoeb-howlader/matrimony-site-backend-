<!DOCTYPE html>
<html>
<head>
    <title>বায়োডাটা অনুমোদিত</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 20px; margin: 0;">
    <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 12px; border-top: 6px solid #10b981; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">

        <div style="text-align: center; margin-bottom: 20px;">
            <div style="background-color: #ecfdf5; width: 60px; height: 60px; border-radius: 50%; display: inline-block; line-height: 60px;">
                <span style="font-size: 30px;">🎉</span>
            </div>
        </div>

        <h2 style="color: #111827; text-align: center; margin-bottom: 10px;">আলহামদুলিল্লাহ!</h2>
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
            আসসালামু আলাইকুম <strong>{{ $userName }}</strong>,<br><br>
            আনন্দের সাথে জানানো যাচ্ছে যে, আপনার জমাকৃত বায়োডাটাটি <strong>(নং: {{ $biodataNo }})</strong> আমাদের অ্যাডমিন প্যানেল থেকে সফলভাবে অনুমোদিত হয়েছে। আপনার বায়োডাটাটি এখন ওয়েবসাইটে লাইভ আছে!
        </p>

        <div style="text-align: center; margin: 35px 0;">
            <a href="{{ $publicUrl }}" style="background-color: #542875; color: #ffffff; padding: 14px 30px; text-decoration: none; font-weight: bold; border-radius: 8px; font-size: 16px; display: inline-block;">বায়োডাটা দেখুন</a>
        </div>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 25px 0;">
        <p style="color: #9ca3af; font-size: 13px; text-align: center;">
            আমাদের ম্যাট্রিমনি প্ল্যাটফর্মের সাথে থাকার জন্য আন্তরিক ধন্যবাদ।<br>
            <strong>ম্যাট্রিমনি টিম</strong>
        </p>
    </div>
</body>
</html>
