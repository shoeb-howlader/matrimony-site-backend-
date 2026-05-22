<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>প্যাকেজ ক্রয় সফল</title>
</head>
<body style="font-family: 'Arial', sans-serif; background-color: #f4f7f9; padding: 20px; margin: 0; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 16px; border-top: 8px solid #542875; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">

        <div style="text-align: center; margin-bottom: 25px;">
            <div style="background-color: #f3e8ff; width: 80px; height: 80px; border-radius: 50%; display: inline-block; line-height: 85px;">
                <span style="font-size: 40px;">✅</span>
            </div>
        </div>

        <h2 style="color: #111827; text-align: center; margin-bottom: 10px; font-size: 24px;">পেমেন্ট সফল হয়েছে!</h2>
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6; text-align: center;">
            আসসালামু আলাইকুম <strong>{{ $userName }}</strong>,<br>
            আপনার পেমেন্টটি সফলভাবে সম্পন্ন হয়েছে। আপনার কেনা প্যাকেজটি এখন আপনার অ্যাকাউন্টে সক্রিয়।
        </p>

        <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 25px; margin: 30px 0; border-radius: 12px;">
            <h3 style="margin-top: 0; color: #374151; font-size: 18px; border-bottom: 2px solid #542875; padding-bottom: 10px; margin-bottom: 20px; display: inline-block;">অর্ডার সামারি</h3>

            <table style="width: 100%; border-collapse: collapse; font-size: 15px;">
                <tr>
                    <td style="padding: 10px 0; color: #6b7280; border-bottom: 1px solid #f3f4f6;">প্যাকেজের নাম</td>
                    <td style="padding: 10px 0; text-align: right; color: #111827; font-weight: bold; border-bottom: 1px solid #f3f4f6;">{{ $packageName }}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; color: #6b7280; border-bottom: 1px solid #f3f4f6;">ট্রানজেকশন আইডি</td>
                    <td style="padding: 10px 0; text-align: right; color: #542875; font-family: monospace; font-weight: bold; border-bottom: 1px solid #f3f4f6;">{{ $transactionId }}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; color: #6b7280; border-bottom: 1px solid #f3f4f6;">পেমেন্ট মেথড</td>
                    <td style="padding: 10px 0; text-align: right; color: #111827; border-bottom: 1px solid #f3f4f6;">{{ strtoupper($paymentMethod) }}</td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; color: #6b7280; border-bottom: 2px solid #e5e7eb;">প্রাপ্ত কানেকশন</td>
                    <td style="padding: 10px 0; text-align: right; color: #10b981; font-weight: bold; border-bottom: 2px solid #e5e7eb;">+{{ $connections }} টি</td>
                </tr>
                <tr>
                    <td style="padding: 15px 0 0 0; color: #111827; font-weight: bold; font-size: 17px;">মোট পরিশোধিত</td>
                    <td style="padding: 15px 0 0 0; text-align: right; color: #542875; font-weight: bold; font-size: 20px;">৳ {{ $amount }}</td>
                </tr>
            </table>

            <div style="margin-top: 20px; padding: 12px; background-color: #fffbeb; border: 1px dashed #f59e0b; border-radius: 8px; text-align: center;">
                <p style="margin: 0; color: #92400e; font-size: 14px; font-weight: bold;">
                    📌 নোট: এই প্যাকেজের মাধ্যমে আপনি নতুন {{ $connections }} টি বায়োডাটার যোগাযোগের তথ্য দেখতে পারবেন।
                </p>
            </div>
        </div>

        <div style="text-align: center; margin-bottom: 30px;">
            <a href="{{ $dashboardUrl }}" style="background-color: #542875; color: #ffffff; padding: 16px 35px; text-decoration: none; font-weight: bold; border-radius: 12px; font-size: 16px; display: inline-block; box-shadow: 0 4px 10px rgba(84, 40, 117, 0.3);">পছন্দের বায়োডাটা খুঁজুন</a>
        </div>

        <p style="color: #6b7280; font-size: 14px; text-align: center; line-height: 1.5;">
            আপনার যদি এই লেনদেন নিয়ে কোনো প্রশ্ন থাকে, তবে আমাদের সাপোর্ট টিমের সাথে যোগাযোগ করুন।
        </p>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

        <p style="color: #9ca3af; font-size: 12px; text-align: center;">
            এটি একটি সিস্টেম জেনারেটেড ইমেইল। দয়া করে এখানে রিপ্লাই করবেন না।<br>
            &copy; {{ date('Y') }} আপনার ওয়েবসাইটের নাম। সর্বস্বত্ব সংরক্ষিত।
        </p>
    </div>
</body>
</html>
