<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="utf-8" />
    <title> GL Homes Limited</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta
        content="GL Homes Limited is a leading real estate, construction and Automation tech company specializing in residential and commercial properties. With a commitment to quality and innovation, we create exceptional living spaces and infrastructure that enhance communities and lifestyles. "
        name="description" />
    <meta content="GL Homes" name="author" />
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/common.css" rel="stylesheet">
    <link href="css/theme-02.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css" />
    <style>
        /* Align phone input inside your form design */
        .intl-tel-input {
            width: 100%;
        }
        .intl-tel-input .form-control {
            height: 100%;
        }
        .intl-tel-input input {
            width: 100%;
            padding-left: 58px !important; /* adjusts for dial code */
        }
    </style>


    <!-- favicon -->
    <link rel="shortcut icon" href="images/Logo-sm.png">

    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@200;300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Tailwind css Cdn -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script src="tailwind.config.js"></script>
    <!-- SweetAlert2 CSS + JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body class="font-body">

    <!-- Navbar Start -->
    <div class="flex flex-col bg-white">
        <nav id="nav" class="py-6 md:border-b-0 border-b" role="navigation">
            <div class="container mx-auto px-2 flex items-center flex-no-wrap justify-between">
                <a href="https://glhomesltd.com" class="flex items-center">
                    <img src="images/Logo-sm.png" alt="GL Homes Ltd" class="h-20">
                </a>
                <div
                    class="w-auto transition-all ease-out duration-300 transition-none md:flex-grow flex items-center justify-between opacity-0 opacity-100">
                    <ul class="flex duration-300 ease-out sm:transition-none ms-auto md:mt-0">
                        <li>
                            <a class="pt-3 px-3 md:px-6 font-medium font-secondary block text-blue-500 text-lg" href="#"
                                title="https://glhomesltd.com">GL Homes Limited</a>
                        </li>
                        <li>
                            <a class="pt-3 px-3 md:px-6 font-medium font-secondary block text-black/70 hover:text-blue-500 text-lg"
                                href="https://glhomesltd.com#companies" title="About">Our Companies</a>
                        </li>
                        
                    </ul>
                </div>
            </div>
        </nav>
    </div>



    <div class="forny-container">
        
<div class="forny-inner">
    <div class="forny-form" id="register">
        <div class="text-center">
            <h2 class="text-4xl/snug font-semibold text-transparent bg-clip-text bg-gradient-to-r from-red-500 via-blue-600 to-blue-400 mb-2">Register for GLHOMES BMC</h2>
            <p class="text-1xl text-center font-semibold mb-5">This page is only for GLHomes Employees <br>
            <strong>NOTE:</strong>We will contact you after payment</p>
        </div>
        <form>
     <div class="form-group">
        <div class="input-group">
            <input required class="form-control" name="name" id="name" type="text" placeholder="Full Name">   
        </div>
    </div>        
    <div class="form-group">
        <div class="input-group">
            <input required class="form-control" name="email" id="email" type="email" placeholder="Personal Email Address">
        </div>
    </div>

    <!-- Phone Number Input -->
    <div class="form-group">
        <div class="input-group">
            <input required class="form-control" id="phone" type="tel" name="phone" placeholder="Phone Number">
        </div>
    </div>

    <!-- Gender Dropdown -->
<div class="form-group">
    <div class="input-group">
        <select id="gender" class="form-control" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
        </select>
    </div>
</div>

<div class="form-group">
        <div class="input-group">
            <input required class="form-control" id="crole" type="crole" name="crole" placeholder="Your role in the Company">
        </div>
    </div>


    
    <div class="row mt-6 mb-6">
        <div class="col-6 d-flex align-items-center"></div>
    </div>
    <strong class="text-1xl text-blue-600 mb-3">Any issue? message masterclass@glhomesltd.com</strong><br><br>
<button class="btn text-white bg-blue-600 hover:bg-blue-700 btn-block col-xl-4 col-lg-4 col-md-4 col-12" id="pn" type="button" onclick="pay_now(this);">Register now</button>
</form>

    </div>
</div>

    </div>
    <!-- Section End -->

   


    <footer class="py-8">
        <div class="container mx-auto px-10">

            <div class="flex">
                <div class="w-full">
                    <div class="text-center">
                        <p class="text-muted"> ©
                            <script>document.write(new Date().getFullYear())</script> GL Homes Limited. By <a class="font-medium" href="https://tech.glhomesltd">GL Homes Tech</a>
                        </p>

                    </div>
                </div>
            </div>
            <!-- end row -->

        </div>
    </footer>
 <script src="js2/"></script>
    <script src="js2/bootstrap.min.js"></script>
    <script src="js2/main.js"></script>
    <script src="js2/demo.js"></script>
    <script>

        //Show and hide hamburguer menu in small screens 
        const menu = document.getElementById("menu");
        const ulMenu = document.getElementById("ulMenu");

        function menuToggle() {
            menu.classList.toggle('navbar-show')
        }

        // Browser resize listener
        window.addEventListener("resize", menuResize);

        // Rezise menu if user changing the width with responsive menu opened
        function menuResize() {
            // first get the size from the window
            const window_size = window.innerWidth || document.body.clientWidth;
            if (window_size > 640) {
                menu.classList.remove('navbar-show');
            }
        }
    </script>

<!-- Phone Input Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
<script>
    const phoneInput = document.querySelector("#phone");
    window.intlTelInput(phoneInput, {
        separateDialCode: true,
        preferredCountries: ["ng", "us", "gb"],
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.min.js"
    });
</script>


<script>
   

async function pay_now(button) {

    const fullname = document.getElementById("name").value.trim();
    const email = document.getElementById("email").value.trim();
    const phoneInput = window.intlTelInputGlobals.getInstance(document.getElementById("phone"));
    const phone = phoneInput.getNumber();
    const crole = document.getElementById("crole").value.trim();
    const gender = document.getElementById("gender").value;

    // Validation
    if (!fullname) return alert("Please enter your full name.");
    if (!email || !/^\S+@\S+\.\S+$/.test(email)) return alert("Please enter a valid email.");
    if (!phone) return alert("Please enter a valid phone number.");
    if (!gender) return alert("Please select your gender.");
    if (!crole) return alert("Please enter your role in the company!");

    const data = {
        fullname,
        email,
        phone,
        crole,
        gender
    };
  
        button.disabled = true;
        //payUsdButton.disabled = true;
        button.textContent = "Please wait...";

        await fetch("emp_reg_process.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response => {
        var msg = response.message
            if (response.status === "success") {
                Swal.fire({
        icon: 'success',
        title: 'Success',
        text: msg,
        confirmButtonColor: '#3085d6'
    });
            } else {
                 Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: msg,
        confirmButtonColor: '#d33'
    });
            }
        })
        .catch(err => {
            console.error(err);
            alert("An error occurred. Please try again.");
        });

        button.disabled = false;
        //payUsdButton.disabled = false;
        button.textContent = "Register now";
    //}
}
</script>


</body>

</html>