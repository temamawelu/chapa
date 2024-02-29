<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Chapa\Chapa\Facades\Chapa as Chapa;

use App\Models\Donation;
use Illuminate\Support\Facades\Validator;

class DonationController extends Controller
{
        /**
     * Initialize Rave payment process
     * @return void
     */
    protected $reference;

    public function __construct(){
        $this->reference = Chapa::generateReference();

    }


    public function store(Request $request)
    {
         // Generate a payment reference
         $reference =$this->reference;

         // Validate the request data
         $validator = Validator::make($request->all(), [
             'first_name' => 'required|string',
             'last_name' => 'required|string',
             'email' => 'required|email',
             'amount' => 'required|numeric|min:0',
         ]);
 
         if ($validator->fails()) {
             return redirect('/donate')->withErrors($validator)->withInput();
         }
                 // Initialize payment data
        $data = [
            'amount' => $request->amount,
            'email' => $request->email,
            'tx_ref' => $reference,
            'currency' => "ETB",
            'callback_url' => route('callback', $reference),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            "customization" => [
                "title" => 'Test Donation',
                "description" => "Donation for a good cause"
            ]
        ];

   // Initialize payment using Chapa (assuming it's a payment gateway)
   $payment = Chapa::initializePayment($data);

        // Create donation record
        $donation = Donation::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'tx_ref'     => $reference,
            'amount'     => $request->amount,
            'status'     => ($payment['status'] === 'success') ? 'pending' : 'fail' // Set status based on payment response
        ]);

        if ($payment['status'] !== 'success') {
            return redirect('/')->with('error', 'Sorry, payment was not successful');
        }

        // Redirect user to the checkout URL
        return redirect($payment['data']['checkout_url']);
 
    }

    public function callback($reference)
    {
        // Verify transaction using Chapa
        $data = Chapa::verifyTransaction($reference);
    
        // Check if payment is successful
        if ($data['status'] == 'success') {
            // Update donation status to 'success' if donation found
            $donation = Donation::where('tx_ref', $reference)->first();
    
            if ($donation) {
                $donation->update(['status' => 'success']);
            } else {
                // Handle case where donation record is not found
                Log::error('Donation record not found for reference: ' . $reference);
            }
    
            $message = "Payment is Successful";
            return redirect('/')->with('success', $message);
        } else {
            $message = "Sorry, Payment not successful";
            return redirect('/')->with('error', $message);
        }
    }
 


}
