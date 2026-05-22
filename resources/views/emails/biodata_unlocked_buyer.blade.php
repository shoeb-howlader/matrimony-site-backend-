<!DOCTYPE html>
<html lang="bn">
<body style="font-family: Arial, sans-serif; background-color: #f4f7f9; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 12px; border-top: 6px solid #10b981; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="background-color: #ecfdf5; width: 60px; height: 60px; border-radius: 50%; display: inline-block; line-height: 60px; font-size: 30px;">🔓</div>
        </div>
        <h2 style="color: #111827; text-align: center;">যোগাযোগের তথ্য সংগ্রহ সফল!</h2>
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
            আসসালামু আলাইকুম,<br><br>
            আপনি সফলভাবে <strong>বায়োডাটা নং: {{ $biodataNo }}</strong> এর যোগাযোগের তথ্য সংগ্রহ করেছেন। আপনার অ্যাকাউন্ট থেকে ১টি কানেকশন কাটা হয়েছে।
        </p>

        <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #374151; font-size: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px;">যোগাযোগের বিস্তারিত তথ্য:</h3>

            <p style="margin: 10px 0 5px; color: #374151;"><strong>পাত্র/পাত্রীর নাম:</strong> {{ $candidateName ?? 'দেওয়া নেই' }}</p>
            <p style="margin: 5px 0; color: #374151;"><strong>অভিভাবকের নাম্বার:</strong> <span style="color: #542875; font-weight: bold;">{{ $guardianMobile ?? 'দেওয়া নেই' }}</span></p>
            <p style="margin: 5px 0; color: #374151;"><strong>অভিভাবকের সাথে সম্পর্ক:</strong> {{ $guardianRelationship ?? 'দেওয়া নেই' }}</p>
            <p style="margin: 5px 0 0; color: #374151;"><strong>ইমেইল:</strong> {{ $contactEmail ?? 'দেওয়া নেই' }}</p>
        </div>

        <p style="color: #ef4444; font-size: 14px; text-align: center; font-weight: bold;">বর্তমান কানেকশন ব্যালেন্স: {{ $remainingConnections }} টি</p>

        <div style="text-align: center; margin-top: 25px;">
            <a href="{{ $purchasesUrl }}" style="background-color: #542875; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;">আমার পারচেজ লিস্ট</a>
        </div>
    </div>
</body>
</html>
