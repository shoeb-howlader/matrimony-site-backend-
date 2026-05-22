<!DOCTYPE html>
<html>
<head>
    <title>বায়োডাটা বাতিল</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 20px; margin: 0;">
    <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 12px; border-top: 6px solid #ef4444; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">

        <div style="text-align: center; margin-bottom: 20px;">
            <div style="background-color: #fef2f2; width: 60px; height: 60px; border-radius: 50%; display: inline-block; line-height: 60px;">
                <span style="font-size: 30px;">⚠️</span>
            </div>
        </div>

        <h2 style="color: #111827; text-align: center; margin-bottom: 10px;">বায়োডাটা আপডেট প্রয়োজন</h2>
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
            আসসালামু আলাইকুম <strong>{{ $userName }}</strong>,<br><br>
            দুঃখিত! আপনার জমাকৃত বায়োডাটাটি কিছু অসামঞ্জস্যতার কারণে আমাদের অ্যাডমিন প্যানেল থেকে সাময়িকভাবে বাতিল করা হয়েছে।
        </p>

        <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #991b1b; font-size: 14px; font-weight: bold;">বাতিল হওয়ার কারণ:</p>
            <p style="margin: 5px 0 0 0; color: #b91c1c; font-size: 15px;">{{ $reason }}</p>
        </div>

        <p style="color: #4b5563; font-size: 15px;">দয়া করে আপনার তথ্যগুলো সংশোধন করে পুনরায় জমা দিন।</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $editUrl }}" style="background-color: #542875; color: #ffffff; padding: 14px 30px; text-decoration: none; font-weight: bold; border-radius: 8px; font-size: 16px; display: inline-block;">বায়োডাটা সংশোধন করুন</a>
        </div>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 25px 0;">
        <p style="color: #9ca3af; font-size: 13px; text-align: center;">
            যেকোনো প্রয়োজনে আমাদের সাপোর্ট টিমের সাথে যোগাযোগ করুন।<br>
            <strong>ম্যাট্রিমনি টিম</strong>
        </p>
    </div>
</body>
</html>
