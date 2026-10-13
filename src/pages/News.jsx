import { useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useNewsFeed } from "../utils/news";

export default function News() {
  const navigate = useNavigate();
  const { posts, isLoaded } = useNewsFeed();

  useEffect(() => {
    if (isLoaded && posts.length > 0) {
      navigate(`/news/${posts[0].id}`, { replace: true });
    }
  }, [isLoaded, posts, navigate]);

  const showEmptyState = isLoaded && posts.length === 0;

  return (
    <section className="flex min-h-[50vh] items-center justify-center bg-slate-50 px-5 text-center">
      {showEmptyState && (
        <p className="font-heading text-xl font-bold text-[#002f5f]">
          Aktuell keine neuen News verfügbar
        </p>
      )}
    </section>
  );
}
