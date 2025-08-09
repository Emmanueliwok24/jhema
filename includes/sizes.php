
<style>
  .size-btn {
    color: #6c757d;
    transition: all 0.2s ease-in-out;

  }
  .size-btn.active {
    background-color: black !important;
    color: white !important;
  }
  
</style>

<div class="w-100  rounded shadow-sm">
  <h3 class="">Size Chart</h3>
    <p class="text-muted small">Please select one size that fits you best.</p>

  <div class="accordion  w-100" id="sizeAccordion">

    <!-- Size XS -->
    <div class="accordion-item">
        <button class="size-btn w-100 bg-transparent border" type="button" data-bs-toggle="collapse" data-bs-target="#sizeXS">
          XS
        </button>
      <div id="sizeXS" class="accordion-collapse collapse" data-bs-parent="#sizeAccordion">
        <div class="accordion-body">
          <div class="size-details d-flex justify-content-between">
            <div><strong>US:</strong> 0-2</div>
            <div><strong>UK:</strong> 4-6</div>
            <div><strong>B:</strong> 32-33</div>
            <div><strong>W:</strong> 24-25</div>
            <div><strong>H:</strong> 34-35</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Size S -->
    <div class="accordion-item">
        <button class="size-btn w-100 bg-transparent border" type="button" data-bs-toggle="collapse" data-bs-target="#sizeS">
          S
        </button>
      <div id="sizeS" class="accordion-collapse collapse" data-bs-parent="#sizeAccordion">
        <div class="accordion-body">
          <div class="size-details d-flex justify-content-between">
            <div><strong>US:</strong> 4-6</div>
            <div><strong>UK:</strong> 8-10</div>
            <div><strong>B:</strong> 34-36</div>
            <div><strong>W:</strong> 27-29</div>
            <div><strong>H:</strong> 36-38</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Size M -->
    <div class="accordion-item">
        <button class="size-btn w-100 bg-transparent border" type="button" data-bs-toggle="collapse" data-bs-target="#sizeM">
          M
        </button>
      <div id="sizeM" class="accordion-collapse collapse" data-bs-parent="#sizeAccordion">
        <div class="accordion-body">
          <div class="size-details d-flex justify-content-between">
            <div><strong>US:</strong> 8-10</div>
            <div><strong>UK:</strong> 12-14</div>
            <div><strong>B:</strong> 38-40</div>
            <div><strong>W:</strong> 31-33</div>
            <div><strong>H:</strong> 40-42</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Size L -->
    <div class="accordion-item">
        <button class="size-btn w-100 bg-transparent border" type="button" data-bs-toggle="collapse" data-bs-target="#sizeL">
          L
        </button>
      <div id="sizeL" class="accordion-collapse collapse" data-bs-parent="#sizeAccordion">
        <div class="accordion-body">
          <div class="size-details d-flex justify-content-between">
            <div><strong>US:</strong> 12</div>
            <div><strong>UK:</strong> 16</div>
            <div><strong>B:</strong> 42-43</div>
            <div><strong>W:</strong> 35-37</div>
            <div><strong>H:</strong> 44-47</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Size XL -->
    <div class="accordion-item">
        <button class="size-btn w-100 bg-transparent border" type="button" data-bs-toggle="collapse" data-bs-target="#sizeXL">
          XL
        </button>
      <div id="sizeXL" class="accordion-collapse collapse" data-bs-parent="#sizeAccordion">
        <div class="accordion-body">
          <div class="size-details d-flex justify-content-between">
            <div><strong>US:</strong> 14</div>
            <div><strong>UK:</strong> 18</div>
            <div><strong>B:</strong> 44-46</div>
            <div><strong>W:</strong> 38-39</div>
            <div><strong>H:</strong> 48-49</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Size 2XL -->
    <div class="accordion-item">
        <button class="size-btn w-100 bg-transparent border" type="button" data-bs-toggle="collapse" data-bs-target="#size2XL">
          2XL
        </button>
      <div id="size2XL" class="accordion-collapse collapse" data-bs-parent="#sizeAccordion">
        <div class="accordion-body">
          <div class="size-details d-flex justify-content-between">
            <div><strong>US:</strong> 16-18</div>
            <div><strong>UK:</strong> 20-22</div>
            <div><strong>B:</strong> 47-50</div>
            <div><strong>W:</strong> 40-42</div>
            <div><strong>H:</strong> 50-53</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Size 3XL -->
    <div class="accordion-item">
        <button class="size-btn w-100 bg-transparent border" type="button" data-bs-toggle="collapse" data-bs-target="#size3XL">
          3XL
        </button>
      <div id="size3XL" class="accordion-collapse collapse" data-bs-parent="#sizeAccordion">
        <div class="accordion-body">
          <div class="size-details d-flex justify-content-between">
            <div><strong>US:</strong> 20</div>
            <div><strong>UK:</strong> 24</div>
            <div><strong>B:</strong> 51-53</div>
            <div><strong>W:</strong> 43-45</div>
            <div><strong>H:</strong> 54-56</div>
          </div>
        </div>
      </div>
    </div>

  </div>

    <!-- Size Guide -->
  <div class="mt-3 p-2 border rounded bg-light">
    <small class="text-muted">
      <strong>Size Guide:</strong><br>
      <span class="text-primary">US</span> – United States sizing<br>
      <span class="text-primary">UK</span> – United Kingdom sizing<br>
      <span class="text-primary">H</span> – Half sizes available<br>
      <span class="text-primary">B</span> – Narrow (B width) sizing
    </small>
  </div>
</div>
</div>


<script>
  document.querySelectorAll(".size-btn").forEach(btn => {
    btn.addEventListener("click", function () {
      document.querySelectorAll(".size-btn").forEach(b => b.classList.remove("active"));
      this.classList.add("active");
    });
  });
</script>