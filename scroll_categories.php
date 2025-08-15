<!-- Bootstrap CSS -->

<style>


  .marquee-wrapper {
    background-color: #fff;
    position: sticky;

    width: 100%;
    z-index: 8;
    border-top:.5px solid #1b1b1b40;
    border-bottom:.5px solid #1b1b1b40;
  }

  .marquee-scroll {
    display: inline-block;
    white-space: nowrap;
    animation: scroll-left 100s linear infinite;
  }

  @keyframes scroll-left {
    0% {
      transform: translateX(0%);
    }
    100% {
      transform: translateX(-50%);
    }
  }

  .category-item {
    display: inline-block;
    margin-right: 16px;
    padding: 8px 18px;
    font-size: 0.95rem;
    font-weight: 500;
    text-transform: capitalize;
    transition: all 0.3s ease;
  }


  @media screen and (max-width: 976px) {
    .category-item {
      font-size: 0.85rem;
      padding: 6px 14px;
      margin-right: 10px;
    }
    .marquee-wrapper{
   overflow: hidden;
    }

  }


   /* Keep your original top bar */
    .top{display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:1200px;margin:20px auto;padding:0 12px}
    .nav{display:flex;gap:16px;flex-wrap:wrap;margin:10px auto;max-width:1200px;padding:0 12px}
    .dropdown{position:relative}
    .dropdown > a{padding:6px 10px;border:1px solid #eee;border-radius:6px;text-decoration:none;color:#111;background:#fafafa;display:inline-block}
    .menu{position:absolute;left:0;top:110%;background:#fff;border:1px solid #eee;border-radius:8px;min-width:280px;padding:10px;display:none;z-index:50}
    .dropdown:hover .menu{display:block}
    .menu h4{margin:6px 0 4px 0;font-size:13px;color:#666}
    .menu a{display:inline-block;margin:4px 6px 0 0;padding:4px 8px;background:#f5f5f5;border-radius:999px;text-decoration:none;color:#111;font-size:12px}
    select{padding:8px}

    /* NEW: Sidebar layout */
    .layout{max-width:1200px;margin:0 auto 40px auto;display:grid;grid-template-columns:280px 1fr;gap:18px;padding:0 12px}
    @media (max-width:980px){ .layout{grid-template-columns:1fr} }

    .sidebar{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:14px;position:sticky;top:10px;height:fit-content}
    .sidehead{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
    .sidehead h3{margin:0;font-size:15px;letter-spacing:.06em;text-transform:uppercase}
    .sidehint{font-size:12px;color:var(--sub)}
    .sidegroup{border-top:1px dashed var(--line);padding-top:12px;margin-top:12px}
    .sidegroup h4{margin:0 0 8px 0;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#555}
    .sidecats a{display:block;padding:8px 10px;border:1px solid var(--line);border-radius:10px;background:#fff;margin-bottom:8px}
    .sidecats a.active{border-color:#111;box-shadow:inset 0 0 0 2px rgba(17,17,17,.06)}
    .chips{display:flex;flex-wrap:wrap;gap:8px}
    .chip{display:inline-block;padding:6px 10px;border:1px solid var(--line);border-radius:999px;background:var(--chip);font-size:12px}
    .chip:hover{background:#fff}

    .filters{max-width:1200px;margin:12px auto 0 auto;padding:0 12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--line);border-radius:999px;background:#eee;font-size:12px}
    .pill .x{font-weight:700;cursor:pointer;opacity:.6}
    .pill .x:hover{opacity:1}

    .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}


</style>

<!-- Smooth Marquee-Style Category Bar -->
<div class="marquee-wrapper  py-2 w-100 " style="top: 0; ">
  <div class="marquee-scroll">
    <!-- Repeat twice for seamless infinite scroll -->
    <span  >

         <?php foreach ($menu as $m): ?>
      <div class="dropdown category-item">
        <a href="shop.php?cat=<?= urlencode($m['slug']) ?>&cur=<?= urlencode($display) ?>"><?= htmlspecialchars($m['name']) ?></a>
        <!-- Keep your small hover menu but we’ll also show all attributes in sidebar -->
        <div class="menu">
          <?php if ($m['allowed']['occasion']): ?>
            <h4>Occasion</h4>
            <?php foreach ($m['allowed']['occasion'] as $o): ?>
              <a href="shop.php?cat=<?= urlencode($m['slug']) ?>&occasion=<?= urlencode($o['value']) ?>&cur=<?= urlencode($display) ?>"><?= htmlspecialchars($o['value']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($m['allowed']['length']): ?>
            <h4>Length</h4>
            <?php foreach ($m['allowed']['length'] as $l): ?>
              <a href="shop.php?cat=<?= urlencode($m['slug']) ?>&length=<?= urlencode($l['value']) ?>&cur=<?= urlencode($display) ?>"><?= htmlspecialchars($l['value']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($m['allowed']['style']): ?>
            <h4>Style</h4>
            <?php foreach ($m['allowed']['style'] as $s): ?>
              <a href="shop.php?cat=<?= urlencode($m['slug']) ?>&style=<?= urlencode($s['value']) ?>&cur=<?= urlencode($display) ?>"><?= htmlspecialchars($s['value']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    </span>
    <span  >

         <?php foreach ($menu as $m): ?>
      <div class="dropdown category-item">
        <a href="shop.php?cat=<?= urlencode($m['slug']) ?>&cur=<?= urlencode($display) ?>"><?= htmlspecialchars($m['name']) ?></a>
        <!-- Keep your small hover menu but we’ll also show all attributes in sidebar -->
        <div class="menu">
          <?php if ($m['allowed']['occasion']): ?>
            <h4>Occasion</h4>
            <?php foreach ($m['allowed']['occasion'] as $o): ?>
              <a href="shop.php?cat=<?= urlencode($m['slug']) ?>&occasion=<?= urlencode($o['value']) ?>&cur=<?= urlencode($display) ?>"><?= htmlspecialchars($o['value']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($m['allowed']['length']): ?>
            <h4>Length</h4>
            <?php foreach ($m['allowed']['length'] as $l): ?>
              <a href="shop.php?cat=<?= urlencode($m['slug']) ?>&length=<?= urlencode($l['value']) ?>&cur=<?= urlencode($display) ?>"><?= htmlspecialchars($l['value']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($m['allowed']['style']): ?>
            <h4>Style</h4>
            <?php foreach ($m['allowed']['style'] as $s): ?>
              <a href="shop.php?cat=<?= urlencode($m['slug']) ?>&style=<?= urlencode($s['value']) ?>&cur=<?= urlencode($display) ?>"><?= htmlspecialchars($s['value']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    </span>



  </div>
</div>

