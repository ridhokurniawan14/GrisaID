<style>
    :root { --bg:#f4f5f7; --card:#fff; --fg:#111827; --muted:#6b7280; --line:#d1d5db; --brand:#d97706; --err:#dc2626; }
    @media (prefers-color-scheme: dark) { :root { --bg:#0b0b0d; --card:#17171a; --fg:#f3f4f6; --muted:#9ca3af; --line:#2f3036; --brand:#f59e0b; --err:#f87171; } }
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; min-height:100dvh; display:grid; place-items:center; padding:1rem; background:var(--bg); color:var(--fg);
           font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .card { width:100%; max-width:24rem; background:var(--card); border:1px solid var(--line); border-radius:1rem; padding:1.5rem; }
    .brand { font-size:1.5rem; font-weight:800; letter-spacing:-.02em; } .brand span { color:var(--brand); }
    .sub { color:var(--muted); font-size:.9rem; margin:.25rem 0 1.25rem; }
    label { display:block; font-size:.8rem; font-weight:600; margin:.9rem 0 .3rem; }
    input[type=text], input[type=password] { width:100%; padding:.7rem .8rem; font-size:1rem; border-radius:.6rem; border:1px solid var(--line); background:transparent; color:inherit; }
    input:focus { outline:2px solid color-mix(in srgb, var(--brand) 55%, transparent); border-color:var(--brand); }
    .check { display:flex; gap:.5rem; align-items:center; font-weight:400; color:var(--muted); margin-top:1rem; }
    button { width:100%; margin-top:1.25rem; padding:.8rem; border:0; border-radius:.6rem; background:var(--brand); color:#111; font-size:1rem; font-weight:700; cursor:pointer; }
    button.ghost { background:transparent; color:var(--muted); border:1px solid var(--line); margin-top:0; font-weight:500; }
    .err { color:var(--err); font-size:.82rem; margin:.35rem 0 0; }
    .hint { color:var(--muted); font-size:.78rem; margin:1rem 0 0; }
</style>
