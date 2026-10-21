import { useRef, useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { Link, useNavigate, useParams } from "react-router-dom";
import FadeInImage from "../components/FadeInImage";
import { formatDate, parseDate, textPreview, useNewsFeed } from "../utils/news";

const newsDayFormatter = new Intl.DateTimeFormat("de-DE", { day: "2-digit" });
const newsMonthFormatter = new Intl.DateTimeFormat("de-DE", { month: "short" });
const DESKTOP_NEWS_LIMIT = 3;

function postImages(post) {
  const images = Array.isArray(post.images)
    ? post.images
      .map((image) => image?.image_path)
      .filter(Boolean)
    : [];

  if (images.length > 0) {
    return images;
  }

  return post.image_path ? [post.image_path] : [];
}

function NewsGallery({ images, title }) {
  const [activeImage, setActiveImage] = useState({ imageKey: "", index: 0 });
  const swipeStartX = useRef(null);
  const suppressEdgeClick = useRef(false);
  const suppressEdgeClickTimeout = useRef(null);
  const imageKey = images.join("|");

  if (images.length === 0) {
    return null;
  }

  const hasMultipleImages = images.length > 1;
  const activeIndex = activeImage.imageKey === imageKey
    ? Math.min(activeImage.index, images.length - 1)
    : 0;
  const setGalleryIndex = (index) => {
    setActiveImage({ imageKey, index });
  };
  const showPrevious = () => {
    setGalleryIndex(activeIndex === 0 ? images.length - 1 : activeIndex - 1);
  };
  const showNext = () => {
    setGalleryIndex(activeIndex === images.length - 1 ? 0 : activeIndex + 1);
  };
  const finishSwipe = (clientX) => {
    if (swipeStartX.current === null) {
      return;
    }

    const distance = clientX - swipeStartX.current;
    swipeStartX.current = null;

    if (Math.abs(distance) < 48) {
      return;
    }

    suppressEdgeClick.current = true;

    if (suppressEdgeClickTimeout.current !== null) {
      window.clearTimeout(suppressEdgeClickTimeout.current);
    }

    suppressEdgeClickTimeout.current = window.setTimeout(() => {
      suppressEdgeClick.current = false;
      suppressEdgeClickTimeout.current = null;
    }, 120);

    if (distance > 0) {
      showPrevious();
    } else {
      showNext();
    }
  };
  const handleEdgeClick = (callback) => {
    if (suppressEdgeClick.current) {
      return;
    }

    callback();
  };
  const handleImageClick = (event) => {
    if (!hasMultipleImages || suppressEdgeClick.current) {
      return;
    }

    if (event.target instanceof Element && event.target.closest("button")) {
      return;
    }

    showNext();
  };

  return (
    <figure className="mb-8 w-full sm:mb-12">
      <div
        className={`relative aspect-video overflow-hidden rounded-lg bg-slate-100 ${
          hasMultipleImages ? "cursor-pointer" : ""
        }`}
        onClick={handleImageClick}
        onPointerDown={(event) => {
          swipeStartX.current = event.clientX;
        }}
        onPointerLeave={(event) => {
          if (event.buttons === 1) {
            finishSwipe(event.clientX);
          }
        }}
        onPointerUp={(event) => {
          finishSwipe(event.clientX);
        }}
      >
        <div
          className="flex h-full transition-transform duration-500 ease-out"
          style={{ transform: `translateX(-${activeIndex * 100}%)` }}
        >
          {images.map((imagePath, index) => (
            <div key={`${imagePath}-${index}`} className="flex h-full min-w-full">
              <FadeInImage
                wrapperClassName="h-full w-full"
                className="block h-full w-full object-cover"
                src={imagePath}
                alt=""
                draggable={false}
                loading={index === 0 ? "eager" : "lazy"}
              />
            </div>
          ))}
        </div>

        {hasMultipleImages && (
          <>
            <button
              type="button"
              className="absolute inset-y-0 left-0 z-10 flex w-24 cursor-w-resize items-center justify-start bg-linear-to-r from-black/35 via-black/10 to-transparent px-3 opacity-0 transition-opacity duration-200 hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none sm:w-32 sm:px-4"
              aria-label="Vorheriges Bild anzeigen"
              onClick={() => {
                handleEdgeClick(showPrevious);
              }}
            >
              <span
                className="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-[#002f5f] shadow-lg"
                aria-hidden="true"
              >
                <ChevronLeft className="h-6 w-6" strokeWidth={2.5} />
              </span>
            </button>
            <button
              type="button"
              className="absolute inset-y-0 right-0 z-10 flex w-24 cursor-e-resize items-center justify-end bg-linear-to-l from-black/35 via-black/10 to-transparent px-3 opacity-0 transition-opacity duration-200 hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none sm:w-32 sm:px-4"
              aria-label="Nächstes Bild anzeigen"
              onClick={() => {
                handleEdgeClick(showNext);
              }}
            >
              <span
                className="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-[#002f5f] shadow-lg"
                aria-hidden="true"
              >
                <ChevronRight className="h-6 w-6" strokeWidth={2.5} />
              </span>
            </button>
          </>
        )}
      </div>

      {hasMultipleImages && (
        <figcaption className="mt-3 flex justify-center">
          <div className="flex items-center gap-1.5" aria-label={`Bildergalerie für ${title}`}>
            {images.map((imagePath, index) => (
              <button
                key={`${imagePath}-dot-${index}`}
                type="button"
                className={`h-2 rounded-full border transition-all duration-300 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 ${
                  index === activeIndex
                    ? "w-7 border-blue-500 bg-blue-500/90"
                    : "w-2 border-slate-300 bg-slate-200/80 hover:border-slate-400 hover:bg-slate-300"
                }`}
                aria-label={`Bild ${index + 1} anzeigen`}
                aria-current={index === activeIndex ? "true" : undefined}
                onClick={() => setGalleryIndex(index)}
              />
            ))}
          </div>
        </figcaption>
      )}
    </figure>
  );
}

export default function NewsDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { posts, isLoaded } = useNewsFeed();
  const [showAllDesktopNews, setShowAllDesktopNews] = useState(false);

  const post = posts.find((item) => String(item.id) === String(id)) || null;

  if (!isLoaded) {
    return (
      <section className="mx-auto max-w-4xl px-5 py-16 sm:px-8 lg:px-12">
        <p className="text-slate-700">News wird geladen.</p>
      </section>
    );
  }

  if (!post) {
    return (
      <section className="mx-auto max-w-4xl px-5 py-16 sm:px-8 lg:px-12">
        <h1 className="font-heading text-3xl font-bold text-[#002f5f]">News nicht gefunden</h1>
        <Link className="mt-6 inline-flex text-blue-700" to="/">
          Zur Startseite
        </Link>
      </section>
    );
  }

  const images = postImages(post);
  const previewPosts = posts.slice(0, DESKTOP_NEWS_LIMIT);
  const currentPostInPreview = previewPosts.some((item) => String(item.id) === String(id));
  const desktopPosts = showAllDesktopNews
    ? posts
    : currentPostInPreview
      ? previewPosts
      : [...previewPosts.slice(0, DESKTOP_NEWS_LIMIT - 1), post];
  const hasMoreDesktopPosts = posts.length > DESKTOP_NEWS_LIMIT;

  return (
    <article className="bg-slate-50">
      <div className="mx-auto grid max-w-7xl gap-8 px-5 py-8 sm:px-8 sm:py-12 lg:grid-cols-[320px_minmax(0,1fr)] lg:gap-12 lg:px-12">
        <aside className="hidden min-w-0 lg:sticky lg:top-8 lg:block lg:self-start">
          <h2 className="font-heading text-sm font-bold uppercase tracking-wide text-blue-700">News</h2>
          <nav className="mt-4 grid gap-3 sm:grid-cols-2 lg:mt-5 lg:grid-cols-1" aria-label="Verfügbare News-Beiträge">
            {desktopPosts.map((item) => {
              const isCurrent = String(item.id) === String(id);
              const itemDate = parseDate(item.created_at);

              return (
                <Link
                  key={item.id}
                  to={`/news/${item.id}`}
                  className={`flex min-w-0 gap-3 rounded-md border px-3 py-3 transition hover:text-blue-700 ${
                    isCurrent
                      ? "border-blue-200 bg-blue-50 text-[#002f5f]"
                      : "border-transparent text-slate-700 hover:border-slate-200 hover:bg-white"
                  }`}
                  aria-current={isCurrent ? "page" : undefined}
                >
                  {itemDate && (
                    <span className={`font-heading min-w-11 text-center ${isCurrent ? "text-[#002f5f]" : "text-slate-500"}`}>
                      <span className="block text-3xl font-bold leading-none">{newsDayFormatter.format(itemDate)}</span>
                      <span className="mt-1 block text-xs font-bold uppercase leading-none">{newsMonthFormatter.format(itemDate)}</span>
                    </span>
                  )}
                  <span className={`min-w-0 border-l pl-3 ${isCurrent ? "border-blue-200" : "border-slate-300"}`}>
                    <span className="block truncate font-heading text-base font-bold text-slate-900">{item.title}</span>
                    <span className="mt-1 block truncate text-sm leading-snug text-slate-600">
                      {textPreview(item.body_html)}
                    </span>
                  </span>
                </Link>
              );
            })}
          </nav>
          {hasMoreDesktopPosts && (
            <button
              type="button"
              className="mt-4 inline-flex font-heading text-sm font-bold text-blue-700 transition hover:text-blue-900"
              onClick={() => {
                setShowAllDesktopNews((isShown) => !isShown);
              }}
            >
              {showAllDesktopNews ? "Weniger News anzeigen" : "Alle News anzeigen"}
            </button>
          )}
        </aside>

        <div className="min-w-0 max-w-4xl lg:pl-2">
          <NewsGallery images={images} title={post.title} />
          <Link className="font-heading text-sm font-bold text-blue-700" to="/">
            Zurück zur Startseite
          </Link>
          <h1 className="mt-5 wrap-break-word font-heading text-3xl font-bold leading-tight text-[#002f5f] sm:mt-6 sm:text-5xl">
            {post.title}
          </h1>
          {post.created_at && (
            <p className="mt-4 text-sm font-semibold text-slate-500">
              Vom {formatDate(post.created_at)}
            </p>
          )}
          <div
            className="news-content mt-7 min-w-0 wrap-break-word sm:mt-8"
            dangerouslySetInnerHTML={{ __html: post.body_html }}
          />
          <div className="mt-8 lg:hidden">
            <label
              className="font-heading text-sm font-bold uppercase tracking-wide text-blue-700"
              htmlFor="mobile-news-select"
            >
              Weitere News
            </label>
            <select
              id="mobile-news-select"
              className="mt-2 block w-full rounded-md border border-slate-300 bg-white px-3 py-3 font-heading text-base font-bold text-[#002f5f] shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
              value={String(id)}
              onChange={(event) => {
                navigate(`/news/${event.target.value}`);
              }}
            >
              {posts.map((item) => (
                <option key={item.id} value={String(item.id)}>
                  {item.created_at ? `${formatDate(item.created_at)} - ` : ""}
                  {item.title}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>
    </article>
  );
}
