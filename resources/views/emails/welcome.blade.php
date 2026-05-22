<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>স্বাগতম</title>
</head>
<body style="font-family: 'Arial', sans-serif; background-color: #f4f7f9; padding: 20px; margin: 0; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 16px; border-top: 8px solid #542875; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">

        <div style="text-align: center; margin-bottom: 25px;">
            <div style="background-color: #f3e8ff; width: 80px; height: 80px; border-radius: 50%; display: inline-block; line-height: 85px;">
                <span style="font-size: 40px;">👋</span>
            </div>
        </div>

        <h2 style="color: #111827; text-align: center; margin-bottom: 10px; font-size: 24px;">স্বাগতম, {{ $userName }}!</h2>
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6; text-align: center;">
            আমাদের ম্যাট্রিমনি প্ল্যাটফর্মে যুক্ত হওয়ার জন্য আপনাকে আন্তরিক ধন্যবাদ। আমরা আপনার সুন্দর ও বরকতময় ভবিষ্যতের জন্য শুভকামনা জানাই।
        </p>

        <div style="background-color: #f9fafb; border: 1px dashed #d1d5db; padding: 20px; margin: 30px 0; border-radius: 12px; text-align: center;">
            <h3 style="color: #374151; font-size: 16px; margin-top: 0; margin-bottom: 10px;">আপনার পরবর্তী করণীয়:</h3>
            <p style="color: #6b7280; font-size: 14px; margin: 0;">
                আপনার প্রোফাইল সম্পূর্ণ করুন এবং একটি সুন্দর বায়োডাটা তৈরি করুন, যাতে অন্যান্য ব্যবহারকারীরা আপনাকে সহজেই খুঁজে পায়।
            </p>
        </div>

        <div style="text-align: center; margin-bottom: 30px;">
            <a href="{{ $dashboardUrl }}" style="background-color: #542875; color: #ffffff; padding: 16px 35px; text-decoration: none; font-weight: bold; border-radius: 12px; font-size: 16px; display: inline-block; box-shadow: 0 4px 10px rgba(84, 40, 117, 0.3);">বায়োডাটা তৈরি করুন</a>
        </div>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

        <p style="color: #9ca3af; font-size: 12px; text-align: center;">
            যেকোনো প্রয়োজনে আমাদের সাপোর্ট টিমের সাথে যোগাযোগ করতে পারেন।<br>
            &copy; {{ date('Y') }} আপনার ওয়েবসাইটের নাম। সর্বস্বত্ব সংরক্ষিত।
        </p>
    </div>
</body>
</html>
