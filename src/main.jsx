import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'

import './index.css'
import Datenschutz from './pages/Datenschutz.jsx'
import HomePage from './pages/HomePage.jsx'
import Impressum from './pages/Impressum.jsx'
import Layout from './Layout.jsx'
import Probetraining from "./pages/Probetraining.jsx";
import Termine from "./pages/Termine.jsx";
import Training from "./pages/Training.jsx";
import Verein from "./pages/Verein.jsx";

const basename = import.meta.env.BASE_URL === '/'
  ? undefined
  : import.meta.env.BASE_URL.replace(/\/$/, '')

const router = createBrowserRouter(
  [
    {
      path: '/',
      element: <Layout />,
      children: [
        {
          index: true,
          element: <HomePage />,
        },
        {
          path: 'index.html',
          element: <HomePage />,
        },
        {
          path: 'probetraining',
          element: <Probetraining />,
        },
        {
          path: 'verein',
          element: <Verein />,
        },
        {
          path: 'training',
          element: <Training />,
        },
        {
          path: 'termine',
          element: <Termine />,
        },
        {
          path: 'impressum',
          element: <Impressum />,
        },
        {
          path: 'datenschutz',
          element: <Datenschutz />,
        },
      ],
    },
  ],
  { basename },
)

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
)
