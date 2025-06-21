<aside id="sidebar" class="sidebar">
  <ul class="sidebar-nav" id="sidebar-nav">
    <li class="nav-item">
      <a class="nav-link" href="{{ route('home') }}">
        <i class="bi bi-grid"></i>
        <span>Dashboard</span>
      </a>
    </li>

    <li class="nav-heading">Sales</li>
    <li class="nav-item">
      <a class="nav-link collapsed" href="{{ route('invoices.index') }}">
        <i class="bi bi-clipboard2-pulse"></i><span>Invoice Management</span>
      </a>
    </li>

    <!-- <li class="nav-heading">Report Center</li>
    <li class="nav-item">
      <a class="nav-link collapsed" href="{{ route('reports.employee_by_designation') }}">
        <i class="bi bi-clipboard2-pulse"></i><span>Employee By Designation</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link collapsed" href="{{ route('reports.employee_by_work_county') }}">
        <i class="bi bi-clipboard2-pulse"></i><span>Employee By Work-County</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link collapsed" href="{{ route('reports.employee_by_skill_level') }}">
        <i class="bi bi-clipboard2-pulse"></i><span>Employee By Skill-Level</span>
      </a>
    </li>

    <li class="nav-heading">Data Import</li>
    <li class="nav-item">
      <a class="nav-link collapsed" href="{{ route('file_imports.index') }}">
        <i class="bi bi-clipboard2-pulse"></i><span>Employee Import</span>
      </a>
    </li> -->

    <li class="nav-heading">Settings</li>
    <!-- user management -->
    <li class="nav-item">
      <a class="nav-link collapsed" href="{{ route('user_profiles.index') }}">
        <i class="bi bi-people"></i><span>Users</span>
      </a>
    </li>
  </ul>
</aside>
