import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";

export default function News() {
  const [isLoaded, setIsLoaded] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    let isMounted = true;

    fetch("/news-feed.php", { cache: "no-store" })
      .then((response) => (response.ok ? response.json() : { posts: [] }))
      .then((data) => {
        if (!isMounted) {
          return;
        }

        const posts = Array.isArray(data.posts) ? data.posts : [];

        if (posts.length > 0) {
          navigate(`/news/${posts[0].id}`, { replace: true });
          return;
        }

        setIsLoaded(true);
      })
      .catch(() => {
        if (isMounted) {
          setIsLoaded(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [navigate]);

  return (
    <section className="flex min-h-[50vh] items-center justify-center bg-slate-50 px-5 text-center">
      {isLoaded && (
        <p className="font-heading text-xl font-bold text-[#002f5f]">
          Aktuell keine neuen News verfügbar
        </p>
      )}
    </section>
  );
}
