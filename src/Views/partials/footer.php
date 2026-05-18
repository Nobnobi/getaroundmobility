<!-- filepath: c:\xampp\htdocs\GetAroundMobility\src\Views\partials\footer.php -->
<!-- FOOTER -->
<footer class="bg-[#062C41] text-white px-6 pt-10 pb-6 mt-8 font-[Barlow]">
  <div class="max-w-7xl mx-auto">
    
    <!-- UPPER PART -->
    <div class="flex flex-wrap gap-8 justify-between">
      
      <!-- LOGO AND DESIGN -->
      <div class="w-full md:w-1/2 lg:w-1/4">
        <img src="/img/Original logo.svg" alt="GetAroundMobility logo" class="mb-4 w-40" />
        <p class="text-sm text-gray-300">
          Top rated for mobility scooter rental in Las Vegas with the most 5 star reviews on Google and Yelp. Our family business has been renting mobility scooters, power chairs, wheel chairs, walkers, and knee walkers in Las Vegas since 2012. We also specialize in sales and are authorized dealers of Pride Mobility, Golden Technology, and Drive products.
        </p>
      </div>

      <!-- LINKS WRAPPER -->
      <div class="flex flex-wrap gap-8 flex-1 justify-start text-sm">
                
        <!-- Company -->
        <div class="min-w-[120px]">
          <h5 class="font-semibold mb-2">Company</h5>
          <ul class="space-y-1 text-gray-400">
            <li>
              <button id="aboutUsBtn" class="hover:underline focus:outline-none text-gray-400 hover:text-white transition cursor-pointer">About us</button>
            </li>

            <a href="https://secure.na1.echosign.com/public/esignWidget?wid=CBFCIBAA3AAABLblqZhBqgEFJParzEEdkJ7giZu81tbwW-7vwXDXC64cX8wdf-XwNUF8kFmcy97TQ73CCMd4*" class="cursor-pointer hover:underline focus:outline-none text-gray-400 hover:text-white transition" target="_blank" rel="noopener noreferrer">
              <li>Contract</li>
            </a>
            
          </ul>
        </div>

        <!-- Resources -->
        <!-- <div class="min-w-[120px]">
          <h5 class="font-semibold mb-2">Resources</h5>
          <ul class="space-y-1 text-gray-400">
            <li>Blog</li>
            <li>Newsletter</li>
            <li>Events</li>
            <li>Help centre</li>
            <li>Tutorials</li>
            <li>Support</li>
          </ul>
        </div> -->

        <!-- Legal -->
        <!-- <div class="min-w-[120px]">
          <h5 class="font-semibold mb-2">Legal</h5>
          <ul class="space-y-1 text-gray-400">
            <li>Terms</li>
            <li>Privacy</li>
            <li>Cookies</li>
            <li>Licenses</li>
            <li>Settings</li>
            <li>Contract</li>
          </ul>
        </div> -->

      </div>
    </div>

    <!-- LOWER PART -->
    <div class="mt-10 border-t border-gray-700 pt-4 text-sm text-gray-400 flex flex-col md:flex-row justify-between items-center">
      <p>© 2077 Get Around Mobility. All rights reserved.</p>
      <div class="flex gap-4 mt-2 md:mt-0">
        <!-- Replace with actual icons or SVG -->
        <a href="#" aria-label="Twitter" class="hover:text-white">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22.46 6c-.77.35-1.6.58-2.47.69a4.3 4.3 0 001.88-2.37 8.6 8.6 0 01-2.72 1.04 4.28 4.28 0 00-7.3 3.9 12.14 12.14 0 01-8.8-4.46 4.26 4.26 0 001.33 5.7 4.25 4.25 0 01-1.94-.54v.05a4.28 4.28 0 003.44 4.2 4.3 4.3 0 01-1.93.07 4.29 4.29 0 004 2.98A8.6 8.6 0 012 19.54a12.14 12.14 0 006.56 1.92c7.88 0 12.2-6.53 12.2-12.2 0-.19-.01-.38-.02-.57A8.7 8.7 0 0022.46 6z"/></svg>
        </a>
        <a href="#" aria-label="LinkedIn" class="hover:text-white">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5a2.5 2.5 0 110 5 2.5 2.5 0 010-5zM2 8.98h6v13H2v-13zM14.5 8.98a4.5 4.5 0 00-4.5 4.5v8.5h6v-8.5a1.5 1.5 0 013 0v8.5h6v-9.5a6.5 6.5 0 00-6.5-6.5z"/></svg>
        </a>
        <a href="#" aria-label="Facebook" class="hover:text-white">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12a10 10 0 10-11.63 9.87v-7H8v-3h2.37V9.5c0-2.33 1.38-3.63 3.5-3.63 1.02 0 2.1.18 2.1.18v2.3h-1.18c-1.17 0-1.54.72-1.54 1.46V12h2.63l-.42 3h-2.2v7A10 10 0 0022 12z"/></svg>
        </a>
      </div>
    </div>

  </div>
</footer>

  <!-- About Us Modal -->
  <div id="aboutUsModal" class="fixed inset-0 z-50 flex items-start md:items-center justify-center hidden bg-black/60 backdrop-blur-sm font-[Barlow] px-4 pb-4 md:p-6 overflow-y-auto">

    <div class="bg-white rounded-2xl md:rounded-3xl shadow-2xl max-w-[86vw] md:max-w-3xl w-full relative overflow-y-auto max-h-[72vh] md:max-h-[88vh] border border-[#0086C9] mt-28 md:mt-0">

      <!-- Close Button -->
            <button id="closeAboutUsModal"
              class="absolute top-2 right-2 md:top-4 md:right-5 z-10 text-white/90 hover:text-white text-xl md:text-3xl font-bold transition bg-black/30 rounded-full w-8 h-8 md:w-10 md:h-10 leading-7 md:leading-9 text-center">
        &times;
      </button>

      <!-- Header -->
      <div class="bg-gradient-to-r from-[#0086C9] to-[#062C41] p-3 md:p-8 text-center">
        <div class="mb-2 flex justify-center">
          <img src="/img/Original logo.svg" alt="GetAroundMobility logo" class="w-16 md:w-32 drop-shadow-lg" />
        </div>
        <h2 class="text-lg md:text-3xl font-bold text-white font-[Barlow] leading-tight">
          About Get Around Mobility
        </h2>
        <p class="mt-1 text-white/90 text-xs md:text-sm">
          Your Partner in Mobility
        </p>
      </div>

      <!-- Content -->
      <div class="p-3 md:p-8 space-y-3 md:space-y-6 text-gray-700">

        <!-- Intro -->
        <p class="text-center text-[11px] md:text-base leading-relaxed">
          <span class="font-semibold text-[#0086C9]">Get Around Mobility</span> is a
          family-owned business proudly serving Las Vegas with top-quality mobility
          scooter rentals and sales. We are trusted by thousands of customers and
          consistently rated 5-stars on Google and Yelp.
        </p>

        <!-- Features -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-5 text-xs md:text-base">

          <div class="flex items-start gap-2">
            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gradient-to-br from-[#00B4D8] to-[#0086C9] shadow text-white text-xl mt-0.5">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </span>
            <p>
              Wide selection of mobility scooters, power chairs, wheelchairs,
              walkers, and knee walkers.
            </p>
          </div>

          <div class="flex items-start gap-2">
            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gradient-to-br from-[#00B4D8] to-[#0086C9] shadow text-white text-xl mt-0.5">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </span>
            <p>
              Authorized dealers of <strong>Pride Mobility</strong>,
              <strong>Golden Technology</strong>, and <strong>Drive</strong>.
            </p>
          </div>

          <div class="flex items-start gap-2">
            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gradient-to-br from-[#00B4D8] to-[#0086C9] shadow text-white text-xl mt-0.5">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </span>
            <p>
              Flexible rental options for hotels, conventions, vacations, and
              everyday mobility needs.
            </p>
          </div>

          <div class="flex items-start gap-2">
            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gradient-to-br from-[#00B4D8] to-[#0086C9] shadow text-white text-xl mt-0.5">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </span>
            <p>
              Friendly, knowledgeable support focused on comfort, safety, and
              convenience.
            </p>
          </div>
        </div>

        <!-- Mission -->
        <div class="bg-[#F3F9FC] rounded-2xl p-3 md:p-6 text-center">
          <h3 class="text-sm md:text-lg font-semibold text-[#062C41] mb-1.5 md:mb-2">
            Our Mission
          </h3>
          <p class="text-xs md:text-base leading-relaxed">
            To make mobility easy, stress-free, and accessible—so you can enjoy
            your time in Las Vegas without limitations.
          </p>
        </div>

        <!-- Footer CTA -->
        <div class="text-center pt-1 md:pt-4">
          <span
            class="inline-block bg-[#0086C9] text-white px-3 md:px-6 py-1.5 md:py-3 rounded-full text-xs md:text-base font-semibold shadow-md">
            Thank you for choosing Get Around Mobility!
          </span>
        </div>
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var aboutBtn = document.getElementById('aboutUsBtn');
  var modal = document.getElementById('aboutUsModal');
  var closeBtn = document.getElementById('closeAboutUsModal');
  if (aboutBtn && modal && closeBtn) {
    aboutBtn.addEventListener('click', function() {
      modal.classList.remove('hidden');
    });
    closeBtn.addEventListener('click', function() {
      modal.classList.add('hidden');
    });
    modal.addEventListener('click', function(e) {
      if (e.target === modal) modal.classList.add('hidden');
    });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') modal.classList.add('hidden');
    });
  }
});
</script>