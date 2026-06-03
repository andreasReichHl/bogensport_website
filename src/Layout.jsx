import { Outlet } from 'react-router-dom'
import Footer from './components/Footer.jsx';
import Navbar from './components/NavBar.jsx';

export default function Layout() {
  return (
    <div className="layout">
      <header className="layout-header">
        <Navbar />
      </header>
      <main className="layout-main">
        <Outlet />
      </main>
      <footer className="layout-footer">
        <Footer />
      </footer>
    </div>
  );
}
