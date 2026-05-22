<!DOCTYPE html>
<html>
<head>
    <title>পাসওয়ার্ড রিসেট কোড</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 20px;">
    <div style="max-w-md mx-auto background-color: #ffffff; padding: 30px; border-radius: 10px; border-top: 5px solid #542875; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">

        <h2 style="color: #333333; margin-bottom: 10px;">পাসওয়ার্ড রিসেট রিকোয়েস্ট</h2>
        <p style="color: #666666; font-size: 16px;">আপনার অ্যাকাউন্টের পাসওয়ার্ড পরিবর্তন করার জন্য নিচের ৬ ডিজিটের কোডটি ব্যবহার করুন:</p>

        <div style="margin: 30px 0;">
            <span style="background-color: #f3e8ff; color: #542875; padding: 15px 30px; font-size: 28px; font-weight: bold; letter-spacing: 5px; border-radius: 8px; border: 1px dashed #542875;">
                {{ $otp }}
            </span>
        </div>

        <p style="color: #999999; font-size: 13px;">এই কোডটির মেয়াদ মাত্র ১৫ মিনিট। আপনি যদি পাসওয়ার্ড রিসেটের কোনো রিকোয়েস্ট না করে থাকেন, তবে এই ইমেইলটি ইগনোর করুন।</p>
        <hr style="border: none; border-top: 1px solid #eeeeee; margin: 20px 0;">
        <p style="color: #cccccc; font-size: 12px;">ধন্যবাদ, <br>ম্যাট্রিমনি টিম</p>
    </div>
</body>
</html>
