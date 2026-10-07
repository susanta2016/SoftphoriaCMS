// Tunable values from the approved P1 spec (sections 5 and 6). They are starting
// values to calibrate on the real-creative test set; change them only together
// with the golden tests and a before/after verdict comparison.

export const REF_WIDTH = 1080;

// File limits (spec 5.1). Engineering limits, not platform rules.
export const MAX_FILE_BYTES = 1024 * 1024 * 1024;
export const MAX_ANALYSIS_SECONDS = 180;
export const DECODE_TIMEOUT_MS = 5000; // until the video's metadata is known
export const FIRST_FRAME_TIMEOUT_MS = 10000; // then until a first frame is decoded (iOS loads frames lazily)
export const ASPECT_TOLERANCE = 0.01;
export const MIN_WIDTH = 720;
export const MIN_HEIGHT = 1280;

// Sampling (spec 5.2).
export const SAMPLE_INTERVAL_S = 0.5;
export const MAX_SAMPLES = 360;
export const MIN_TRACK_S = 0.5;
export const SCENE_CUT_DIFF = 28; // mean absolute difference (0-255) between 32 x 32 thumbnails

// Overlap thresholds (spec 5.3).
export const CRITICAL_COVERAGE = 0.25;
export const CRITICAL_DURATION_S = 1;
export const WARNING_COVERAGE = 0.05;
export const DETECTION_CONFIDENCE = 0.6;
export const PROXIMITY_PX = 16;

// Scoring (spec 6).
export const ELEMENT_WEIGHTS = { text: 1.0, face: 1.0, logo: 0.8, product: 0.7, other: 0.5 };
export const ELEMENT_TYPES = ['text', 'logo', 'face', 'product', 'other'];
export const IMPORTANT_TYPES = ['text', 'logo', 'face'];
export const SEVERITY_FACTOR = { hard: 1.0, soft: 0.4, info: 0 };
export const RISK_CAP = 0.9;
export const PASS_SCORE = 85;
export const FAIL_SCORE = 60;

// Fix search (spec 6): 1% of shrinking costs as much as moving 10 px.
export const FIX_SCALE_MIN = 0.5;
export const FIX_SCALE_STEP = 0.01;
export const FIX_SCALE_COST_PER_UNIT = 1000;
