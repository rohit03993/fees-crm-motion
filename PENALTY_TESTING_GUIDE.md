# Penalty Testing Guide

This guide will help you test both **Late Fee Penalties** and **GST Penalties** to confirm everything is working correctly.

---

## 🎯 Quick Access Links

### 1. **Penalty Settings Page** (Admin Only)
- **URL:** `/settings/penalties`
- **Navigation:** Top menu → "Settings" (admin only)
- **What to check:**
  - ✅ Late Fee Penalties section (Grace Days, Rate % per day)
  - ✅ GST Penalty for Online Payments section (GST Percentage)
  - ✅ Reminder Settings section (Reminder Cadence)

### 2. **Penalty Ledger** (Student Detail Page)
- **URL:** `/students/{student_id}`
- **Navigation:** Students → Click on any student → Scroll to "Penalty Ledger" section
- **What to check:**
  - ✅ Total penalties amount
  - ✅ Breakdown: Late Fees total + GST Penalties total
  - ✅ Combined table showing both penalty types

---

## 📋 Step-by-Step Testing

### **Test 1: Verify Settings Page is Working**

1. **Login as Admin**
   - Use admin credentials (check `AdminStaffSeeder.php`)
   - Default: `Admin@123` password

2. **Navigate to Settings**
   - Click "Settings" in the top navigation menu
   - OR go directly to: `http://your-domain/settings/penalties`

3. **Check Settings Form**
   - ✅ You should see **3 sections:**
     - **Late Fee Penalties** (Grace Days, Rate % per day)
     - **GST Penalty for Online Payments** (GST Percentage)
     - **Reminder Settings** (Reminder Cadence)

4. **Update GST Percentage**
   - Change GST Percentage to any value (e.g., 18.5 or 20)
   - Click "Save Settings"
   - ✅ Should see success message: "Penalty and reminder settings updated."

---

### **Test 2: Test GST Penalty (Online Allowance Overage)**

1. **Create/Find a Student**
   - Go to Students → Create new student OR open existing student
   - Make sure the student has:
     - Total Programme Fee: ₹80,000
     - Planned Online Collection: ₹30,000 (this is the online allowance limit)

2. **Record Online Payment that Exceeds Allowance**
   - Scroll to "Record Payment" accordion section
   - Select **Payment Type:** "Tuition Fees"
   - Select **Apply to Installment:** Choose any installment
   - Enter **Amount Received:** ₹40,000 (exceeds the ₹30,000 limit)
   - Select **Payment Mode:** "UPI" or "Bank Transfer"
   - Fill in required fields (UTR, Bank, etc.)
   - ✅ **IMPORTANT:** You should see a **GST Penalty Warning** before submitting:
     ```
     GST Penalty Warning
     This payment will exceed the online allowance of ₹30,000.00.
     Excess amount: ₹10,000.00
     GST Penalty: ₹11,800.00 (₹10,000.00 + 18% GST) will be added as a separate charge.
     ```

3. **Submit Payment**
   - Click "Save Payment"
   - ✅ Should see success message

4. **Verify GST Penalty was Created**
   - Scroll down to **"Penalty Ledger"** section
   - ✅ You should see:
     - **Type:** "GST Penalty" (purple badge)
     - **Details:** "GST Penalty (Online overage ₹10,000.00)"
     - **Amount:** ₹11,800.00 (₹10,000 + 18% GST)
     - **Status:** "Pending"
   - ✅ **Summary at top** should show:
     - Total Penalties: ₹11,800.00
     - Late Fees: ₹0.00
     - GST Penalties: ₹11,800.00

5. **Check Miscellaneous Charges**
   - Scroll to "Miscellaneous Charges" section
   - ✅ You should see the GST penalty listed there as well
   - Status should be "Pending"

---

### **Test 3: Test Late Fee Penalty (Overdue Installment)**

1. **Create/Find a Student with Overdue Installment**
   - Go to Students → Open a student
   - OR create a new student with installments due in the past

2. **Manually Create Overdue Installment** (For Testing)
   - You can either:
     - **Option A:** Create a student with installment due date in the past
     - **Option B:** Use the command to simulate penalties for a past date

3. **Run Penalty Command**
   - Open terminal/command prompt
   - Navigate to project directory
   - Run:
     ```bash
     php artisan penalties:apply
     ```
   - OR test with a specific date:
     ```bash
     php artisan penalties:apply --date=2025-01-01
     ```
   - ✅ Should see: "Applied X penalties." message

4. **Verify Late Fee Penalty was Created**
   - Go to the student detail page
   - Scroll to **"Penalty Ledger"** section
   - ✅ You should see:
     - **Type:** "Late Fee" (amber badge)
     - **Details:** Installment #X with due date
     - **Amount:** Calculated based on (outstanding × rate% × days late)
     - **Days Late:** Number of days past grace period
     - **Status:** "Recorded"

5. **Check Settings Used**
   - Default settings (if not changed):
     - **Grace Days:** 5 days
     - **Rate % per day:** 1.5%
   - Example calculation:
     - Outstanding: ₹10,000
     - Days late: 10 days (5 days grace + 5 days late)
     - Penalty: ₹10,000 × 1.5% × 5 = ₹750

---

### **Test 4: Test Both Penalties Together**

1. **Find/Create Student with Both Penalties**
   - Student should have:
     - Overdue installments (for late fee)
     - Online payment that exceeded allowance (for GST penalty)

2. **Check Penalty Ledger**
   - ✅ Should show both types in one table
   - ✅ **Summary should show:**
     - Total Penalties: (Late Fee Total + GST Total)
     - Late Fees: ₹X.XX
     - GST Penalties: ₹X.XX

3. **Verify Sorting**
   - Penalties should be sorted by date (newest first)
   - ✅ Most recent penalties appear at the top

---

## 🔍 Verification Checklist

### **Settings Page:**
- [ ] Can access `/settings/penalties` as admin
- [ ] See all 3 sections (Late Fees, GST, Reminders)
- [ ] Can update GST percentage
- [ ] Success message appears after saving
- [ ] Settings are persisted (refresh page, values remain)

### **GST Penalty:**
- [ ] Warning appears when online payment exceeds allowance
- [ ] Warning shows correct excess amount
- [ ] Warning shows correct GST penalty amount
- [ ] GST penalty is created after payment submission
- [ ] Appears in Penalty Ledger with "GST Penalty" type
- [ ] Appears in Miscellaneous Charges section
- [ ] Summary shows correct GST penalty total

### **Late Fee Penalty:**
- [ ] Command runs successfully: `php artisan penalties:apply`
- [ ] Penalties are created for overdue installments
- [ ] Appears in Penalty Ledger with "Late Fee" type
- [ ] Shows correct installment number and due date
- [ ] Shows correct days late
- [ ] Amount calculation is correct (rate × days × outstanding)
- [ ] Summary shows correct late fee total

### **Penalty Ledger Display:**
- [ ] Shows both Late Fee and GST Penalties together
- [ ] Summary breakdown is correct
- [ ] Type badges are correct (amber for Late Fee, purple for GST)
- [ ] Details column shows appropriate information for each type
- [ ] Status badges are correct
- [ ] Sorted by date (newest first)

---

## 🐛 Troubleshooting

### **Can't see Settings page?**
- ✅ Must be logged in as **Admin**
- ✅ Check user role in database: `users` table → `role` column should be `admin`
- ✅ Try logging out and logging back in

### **GST Penalty not appearing?**
- ✅ Make sure payment is for **Tuition Fees** (not Miscellaneous)
- ✅ Payment mode must be **online** (UPI, Bank Transfer, or Cheque)
- ✅ Amount must **exceed** the online allowance limit
- ✅ Check `misc_charges` table for records with label starting with "GST Penalty"

### **Late Fee Penalty not appearing?**
- ✅ Installment must be **past due date**
- ✅ Installment must be **unpaid** (or partially paid)
- ✅ Must be **past grace period** (default 5 days)
- ✅ Check `penalties` table for records
- ✅ Try running command with a specific date: `php artisan penalties:apply --date=YYYY-MM-DD`

### **Penalty Ledger is empty?**
- ✅ Make sure student has penalties (check database directly)
- ✅ Check that `$lateFeePenalties` and `$gstPenalties` are being loaded correctly
- ✅ Clear browser cache and refresh

---

## 📊 Database Checks

### **Check Settings in Database:**
```sql
SELECT * FROM settings WHERE `key` LIKE 'penalty%';
```

Should show:
- `penalty.grace_days`
- `penalty.rate_percent_per_day`
- `penalty.gst_percentage` ← **NEW!**
- `reminder.cadence_days`

### **Check Late Fee Penalties:**
```sql
SELECT * FROM penalties ORDER BY applied_date DESC LIMIT 10;
```

### **Check GST Penalties (Misc Charges):**
```sql
SELECT * FROM misc_charges WHERE label LIKE 'GST Penalty%' ORDER BY created_at DESC LIMIT 10;
```

---

## ✅ Success Criteria

You can confirm everything is working if:

1. ✅ Settings page loads and shows GST percentage field
2. ✅ GST penalty warning appears when online payment exceeds allowance
3. ✅ GST penalty is created and appears in Penalty Ledger
4. ✅ Late fee penalty can be applied via command
5. ✅ Late fee penalty appears in Penalty Ledger
6. ✅ Both penalties show together in unified view
7. ✅ Summary breakdown is accurate

---

## 🚀 Quick Test Commands

```bash
# Check if command exists
php artisan penalties:apply --help

# Run penalties for today
php artisan penalties:apply

# Run penalties for a specific date (for testing)
php artisan penalties:apply --date=2025-01-01

# Check database settings
php artisan tinker
>>> \App\Models\Setting::where('key', 'like', 'penalty%')->get();
```

---

**Need help?** Let me know what specific test is failing and I can help troubleshoot!

