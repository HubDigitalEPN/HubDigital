import {Matrix3, ShaderMaterial} from 'three';

/** Mundos biológicos en un único buffer y atlas: ningún Mesh ni textura por taxón. */
export function materialMundos3D(pixelRatio, activeRank, atlas) {
    return new ShaderMaterial({transparent: true, depthWrite: true, vertexColors: true,
        uniforms: {pixelRatio: {value: pixelRatio}, referenceDistance: {value: 900}, activeRank,
            worldFromView: {value: new Matrix3()}, organismAtlas: {value: atlas}, selectionPulse: {value: 0}},
        vertexShader: `
            attribute float pointSize;
            attribute float rankId;
            attribute float onPath;
            attribute float organismType;
            attribute float visibleNode;
            attribute float selectedNode;
            uniform float activeRank;
            uniform float pixelRatio;
            uniform float referenceDistance;
            uniform float selectionPulse;
            varying vec3 shade;
            varying float strength;
            varying float organism;
            varying float diameter;
            varying float visibleWorld;
            varying float selectedWorld;
            void main() {
                float matchRank = 1.0 - step(0.1, abs(rankId - activeRank));
                float lit = activeRank < 0.5 ? 1.0 : onPath;
                strength = mix(0.12, 1.0, max(lit, selectedNode));
                shade = color; organism = organismType; visibleWorld = visibleNode; selectedWorld = selectedNode;
                vec4 viewPosition = modelViewMatrix * vec4(position, 1.0);
                gl_Position = projectionMatrix * viewPosition;
                float perspective = clamp(referenceDistance / max(1.0, -viewPosition.z), 0.55, 3.6);
                float emphasis = activeRank < 0.5 ? 1.0 : mix(0.85, 1.25, lit) + 0.25 * matchRank;
                gl_PointSize = pointSize * pixelRatio * perspective * emphasis * (1.0 + selectedNode * selectionPulse * 0.10);
                diameter = gl_PointSize / pixelRatio;
            }`,
        fragmentShader: `
            uniform mat3 worldFromView;
            uniform sampler2D organismAtlas;
            uniform float pixelRatio;
            varying vec3 shade;
            varying float strength;
            varying float organism;
            varying float diameter;
            varying float visibleWorld;
            varying float selectedWorld;
            uniform float selectionPulse;
            void main() {
                vec2 xy = (gl_PointCoord - vec2(0.5)) * vec2(2.0, -2.0);
                float radius2 = dot(xy, xy);
                if (radius2 >= 1.0 || visibleWorld < 0.5) discard;
                float radius = sqrt(radius2);
                // Cobertura suave en píxeles: evita bordes dentados y el aro de neón.
                float feather = min(0.18, 1.4 / max(1.0, diameter * pixelRatio));
                float coverage = 1.0 - smoothstep(0.9 - feather, 0.9 + feather, radius);
                float halo = (1.0 - smoothstep(0.89, 1.0, radius)) * 0.055;
                if (coverage < 0.001) {
                    gl_FragColor = vec4(shade * 0.65, halo * strength);
                    #include <colorspace_fragment>
                    return;
                }
                vec2 sphere = xy / 0.9;
                vec3 normal = normalize(vec3(sphere, sqrt(max(0.0, 1.0 - dot(sphere, sphere)))));
                vec3 light = normalize(vec3(-0.65, 0.8, 1.0));
                float daylight = smoothstep(-0.15, 0.85, dot(normal, light));
                float luminance = dot(shade, vec3(0.2126, 0.7152, 0.0722));
                vec3 pigment = mix(shade, vec3(luminance), 0.18);
                // Microrelieve mate, sin mapas de continentes ni capas de nubes.
                float grain = 0.0;
                if (diameter > 24.0) {
                    vec3 surface = normalize(worldFromView * normal);
                    grain = sin(dot(surface, vec3(89.0, 113.0, 67.0)))
                        * sin(dot(surface, vec3(-127.0, 73.0, 101.0)));
                }
                float reflection = pow(max(0.0, dot(normal, normalize(light + vec3(0.0, 0.0, 1.0)))), 36.0);
                float rim = pow(1.0 - normal.z, 4.0);
                vec3 worldColor = pigment * (0.085 + daylight * 0.68 + grain * 0.012)
                    + vec3(0.035, 0.045, 0.06) * (1.0 - daylight)
                    + vec3(0.65, 0.74, 0.82) * reflection * 0.16
                    + pigment * rim * 0.075;
                // Los puntos pequeños conservan contraste, sin convertirse en medallas.
                worldColor = mix(pigment * 0.66, worldColor, smoothstep(4.0, 12.0, diameter));
                // Grabado anatómico fino en mundos cercanos, integrado en la superficie.
                if (diameter > 40.0) {
                    vec2 uv = asin(clamp(sphere, vec2(-1.0), vec2(1.0))) / 3.14159265 + vec2(0.5);
                    float symbol = texture2D(organismAtlas, vec2((organism + uv.x) / 8.0, uv.y)).a;
                    symbol *= smoothstep(40.0, 72.0, diameter);
                    worldColor = mix(worldColor, worldColor * 0.72 + pigment * 0.15, symbol * 0.52);
                }
                // Pulso sobre la superficie y contraste del propio rango, sin un aura exterior.
                worldColor *= 1.0 + selectedWorld * (0.24 + selectionPulse * 0.40);
                gl_FragColor = vec4(worldColor, max(coverage, halo) * strength);
                #include <colorspace_fragment>
            }`,
    });
}
